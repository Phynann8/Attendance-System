<?php

namespace App\Services;

use App\Models\ClassRoom;
use App\Models\ClassSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduleImportService
{
    /**
     * Map day names to ISO day of week (1 = Monday, ..., 7 = Sunday).
     */
    private const DAY_NAME_MAP = [
        'monday' => 1,
        'mon' => 1,
        'm' => 1,
        'tuesday' => 2,
        'tue' => 2,
        't' => 2,
        'wednesday' => 3,
        'wed' => 3,
        'w' => 3,
        'thursday' => 4,
        'thu' => 4,
        'th' => 4,
        'friday' => 5,
        'fri' => 5,
        'f' => 5,
        'saturday' => 6,
        'sat' => 6,
        'sa' => 6,
        'sunday' => 7,
        'sun' => 7,
        'su' => 7,
    ];

    /**
     * Import class schedules from a CSV file.
     *
     * @param  string  $filePath  Path to CSV file
     * @param  int|null  $campusId  Optional campus ID to scope class lookup
     * @param  bool  $truncate  Whether to clear existing schedules for classes present in the CSV
     * @param  User|null  $actor  The user performing the import (for authorization checks)
     * @return array<string, mixed>
     */
    public function importFromCsv(
        string $filePath,
        ?int $campusId = null,
        bool $truncate = false,
        ?User $actor = null
    ): array {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException("Schedule CSV file not found or unreadable: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open file: {$filePath}");
        }

        // Read header row and handle UTF-8 BOM
        $header = fgetcsv($handle);
        if ($header === false || empty($header)) {
            fclose($handle);
            throw new InvalidArgumentException('The CSV file is empty.');
        }

        // Strip UTF-8 BOM if present on first column header
        $header[0] = preg_replace('/[\x{EF}\x{BB}\x{BF}]/u', '', $header[0]);
        $columns = array_map(fn ($col) => strtolower(trim($col)), $header);

        $expectedColumns = ['class_name', 'day_of_week', 'period_number', 'subject'];
        $missing = array_diff($expectedColumns, $columns);
        if (! empty($missing)) {
            // Also check friendly aliases
            $hasClassName = in_array('class_name', $columns, true) || in_array('class', $columns, true);
            $hasDay = in_array('day_of_week', $columns, true) || in_array('day', $columns, true);
            $hasPeriod = in_array('period_number', $columns, true) || in_array('period', $columns, true);
            $hasSubject = in_array('subject', $columns, true);

            if (! ($hasClassName && $hasDay && $hasPeriod && $hasSubject)) {
                fclose($handle);
                throw new InvalidArgumentException(
                    'CSV must contain at least: class_name, day_of_week, period_number, subject. Found: '.implode(', ', $columns)
                );
            }
        }

        // Create column index map
        $colIndex = [];
        foreach ($columns as $idx => $name) {
            $normalized = match ($name) {
                'class', 'classname', 'class_name' => 'class_name',
                'day', 'dayofweek', 'day_of_week' => 'day_of_week',
                'period', 'periodnumber', 'period_number' => 'period_number',
                'subject', 'course' => 'subject',
                'start_time', 'starttime', 'start' => 'start_time',
                'end_time', 'endtime', 'end' => 'end_time',
                'teacher_email', 'teacher', 'email' => 'teacher_email',
                'is_primary', 'primary' => 'is_primary',
                default => $name,
            };
            $colIndex[$normalized] = $idx;
        }

        $results = [
            'total_rows' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Cache teachers and classes in memory to avoid N+1 queries during import
        $classesCache = [];
        $teachersCache = [];
        $classesToTruncate = [];

        $rows = [];
        $rowNum = 1; // 1 was header

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip empty rows
            if (empty(array_filter($row, fn ($val) => trim($val) !== ''))) {
                continue;
            }

            $results['total_rows']++;

            $className = trim($row[$colIndex['class_name']] ?? '');
            $rawDay = trim($row[$colIndex['day_of_week']] ?? '');
            $rawPeriod = trim($row[$colIndex['period_number']] ?? '');
            $subject = trim($row[$colIndex['subject']] ?? '');
            $startTime = isset($colIndex['start_time']) ? trim($row[$colIndex['start_time']] ?? '') : null;
            $endTime = isset($colIndex['end_time']) ? trim($row[$colIndex['end_time']] ?? '') : null;
            $teacherEmail = isset($colIndex['teacher_email']) ? trim($row[$colIndex['teacher_email']] ?? '') : null;
            $rawPrimary = isset($colIndex['is_primary']) ? trim($row[$colIndex['is_primary']] ?? '') : '0';

            // Validate class_name
            if ($className === '') {
                $results['errors'][] = "Row {$rowNum}: Class name is required.";
                $results['skipped']++;

                continue;
            }

            // Lookup class
            $cacheKey = strtolower($className).'_'.($campusId ?? 0);
            if (! isset($classesCache[$cacheKey])) {
                $classQuery = ClassRoom::where('name', $className);
                if ($campusId) {
                    $classQuery->where('campus_id', $campusId);
                }
                $classesCache[$cacheKey] = $classQuery->first();
            }

            $class = $classesCache[$cacheKey];
            if (! $class) {
                $results['errors'][] = "Row {$rowNum}: Class '{$className}' not found.";
                $results['skipped']++;

                continue;
            }

            // Authorization check
            if ($actor && ! $actor->isSuperAdmin() && $class->campus_id && ! $actor->hasCampusAccess($class->campus_id)) {
                $results['errors'][] = "Row {$rowNum}: You do not have permission to manage schedules for class '{$className}'.";
                $results['skipped']++;

                continue;
            }

            // Parse day_of_week
            $dayOfWeek = $this->parseDayOfWeek($rawDay);
            if ($dayOfWeek === null) {
                $results['errors'][] = "Row {$rowNum}: Invalid day of week '{$rawDay}'.";
                $results['skipped']++;

                continue;
            }

            // Parse period_number
            $periodNumber = (int) $rawPeriod;
            if ($periodNumber < 1 || $periodNumber > 20) {
                $results['errors'][] = "Row {$rowNum}: Period number must be between 1 and 20, given '{$rawPeriod}'.";
                $results['skipped']++;

                continue;
            }

            // Parse subject
            if ($subject === '') {
                $subject = 'General';
            }

            // Parse teacher
            $teacherId = null;
            if ($teacherEmail) {
                $teacherKey = strtolower($teacherEmail);
                if (! isset($teachersCache[$teacherKey])) {
                    $teachersCache[$teacherKey] = User::where('email', $teacherEmail)
                        ->where('role', User::ROLE_TEACHER)
                        ->where('is_active', true)
                        ->first();
                }
                $teacher = $teachersCache[$teacherKey];
                if ($teacher) {
                    if ($class->campus_id && ! $teacher->hasCampusAccess($class->campus_id)) {
                        $results['errors'][] = "Row {$rowNum}: Teacher '{$teacher->name}' ({$teacherEmail}) is not assigned to campus for class '{$className}'.";
                        $results['skipped']++;

                        continue;
                    }
                    $teacherId = $teacher->id;
                } else {
                    $results['errors'][] = "Row {$rowNum}: Active teacher with email '{$teacherEmail}' not found. Falling back to class homeroom teacher.";
                    $teacherId = $class->teacher_id;
                }
            } else {
                $teacherId = $class->teacher_id;
            }

            // Parse primary flag
            $isPrimary = in_array(strtolower($rawPrimary), ['1', 'true', 'yes', 'y', 'primary'], true);

            // Format start and end times
            $startTime = $this->normalizeTime($startTime);
            $endTime = $this->normalizeTime($endTime);

            $rows[] = [
                'class_id' => $class->id,
                'day_of_week' => $dayOfWeek,
                'period_number' => $periodNumber,
                'subject' => $subject,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'teacher_id' => $teacherId,
                'is_primary' => $isPrimary,
                'is_active' => true,
            ];

            if ($truncate && ! in_array($class->id, $classesToTruncate, true)) {
                $classesToTruncate[] = $class->id;
            }
        }

        fclose($handle);

        // Execute database operations inside transaction
        DB::transaction(function () use ($rows, $classesToTruncate, &$results) {
            if (! empty($classesToTruncate)) {
                ClassSchedule::whereIn('class_id', $classesToTruncate)->delete();
            }

            foreach ($rows as $item) {
                $schedule = ClassSchedule::where('class_id', $item['class_id'])
                    ->where('day_of_week', $item['day_of_week'])
                    ->where('period_number', $item['period_number'])
                    ->first();

                if ($schedule) {
                    $schedule->update([
                        'subject' => $item['subject'],
                        'start_time' => $item['start_time'],
                        'end_time' => $item['end_time'],
                        'teacher_id' => $item['teacher_id'],
                        'is_primary' => $item['is_primary'],
                        'is_active' => $item['is_active'],
                    ]);
                    $results['updated']++;
                } else {
                    ClassSchedule::create($item);
                    $results['imported']++;
                }
            }
        });

        return $results;
    }

    /**
     * Parse day representation into 1 (Monday) to 7 (Sunday).
     */
    public function parseDayOfWeek(string $raw): ?int
    {
        $clean = strtolower(trim($raw));
        if (is_numeric($clean)) {
            $num = (int) $clean;

            return ($num >= 1 && $num <= 7) ? $num : null;
        }

        return self::DAY_NAME_MAP[$clean] ?? null;
    }

    /**
     * Normalize time string into HH:MM format.
     */
    private function normalizeTime(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        $time = trim($time);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $matches)) {
            $h = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $m = $matches[2];

            return "{$h}:{$m}";
        }

        return $time;
    }

    /**
     * Generate sample CSV template content.
     */
    public function getSampleCsv(): string
    {
        return "class_name,day_of_week,period_number,subject,start_time,end_time,teacher_email,is_primary\n".
            "Grade 10A,Monday,1,Mathematics,07:30,08:15,teacher@school.test,1\n".
            "Grade 10A,Monday,2,Physics,08:20,09:05,teacher@school.test,0\n".
            "Grade 10A,Monday,3,English,09:20,10:05,teacher@school.test,0\n".
            "Grade 10A,Tuesday,1,Khmer Literature,07:30,08:15,teacher@school.test,1\n".
            "Grade 10A,Tuesday,2,Chemistry,08:20,09:05,teacher@school.test,0\n";
    }
}
