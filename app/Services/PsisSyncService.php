<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\ClassRoom;
use App\Models\Student;
use Generator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PsisSyncService
{
    /**
     * Default campus definitions in case SQL dump has no rms_branch inserts.
     *
     * @var array<int, array<string, mixed>>
     */
    private const DEFAULT_CAMPUSES = [
        1 => ['id' => 1, 'name_en' => 'Chhouk Va', 'name_kh' => 'សាខា ឈូកវ៉ា', 'code' => 'CHV', 'address' => 'បុរីពិភពថ្មី ឈូកវ៉ា ២', 'phone' => '088 888 3199', 'is_active' => true],
        2 => ['id' => 2, 'name_en' => 'Kamboul', 'name_kh' => 'សាខា កំបូល', 'code' => 'KB', 'address' => 'បុរីពិភពថ្មី កំបូល', 'phone' => '096 273 9888', 'is_active' => true],
        3 => ['id' => 3, 'name_en' => 'Kour Srov', 'name_kh' => 'សាខា កួរស្រូវ', 'code' => 'KSR', 'address' => 'បុរីពិភពថ្មី កួរស្រូវ', 'phone' => '096 279 6888', 'is_active' => true],
        4 => ['id' => 4, 'name_en' => 'National Road 3', 'name_kh' => 'សាខា ផ្លូវជាតិលេខ៣', 'code' => 'NR3', 'address' => 'បុរីពិភពថ្មី ផ្លូវជាតិលេខ៣', 'phone' => '096 269 7888', 'is_active' => true],
    ];

    /**
     * Synchronize classes and students from a PSIS SQL dump file.
     *
     * @param  string  $filePath  Absolute path to SQL backup file
     * @param  string|null  $targetYearString  e.g. '2026-2027', '2025-2026', or null for current
     * @param  string|null  $targetCampus  e.g. 'CHV', 'KB', 'KSR', 'NR3' or branch id
     * @param  bool  $dryRun  If true, does not write changes to database
     * @param  callable|null  $progressCallback  Optional callback for status updates
     * @return array<string, mixed> Summary of sync results
     */
    public function syncFromSqlFile(
        string $filePath,
        ?string $targetYearString = null,
        ?string $targetCampus = null,
        bool $dryRun = false,
        ?callable $progressCallback = null
    ): array {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("PSIS SQL backup file not found: {$filePath}");
        }

        $fp = fopen($filePath, 'r');
        if (! $fp) {
            throw new RuntimeException("Unable to open SQL backup file: {$filePath}");
        }

        if ($progressCallback) {
            $progressCallback('Scanning campuses & branches...');
        }

        // Step 1: Scan campuses / branches
        $campuses = self::DEFAULT_CAMPUSES;

        while (($line = fgets($fp)) !== false) {
            if (str_starts_with($line, 'INSERT INTO `rms_branch` VALUES')) {
                foreach ($this->extractTuples($line) as $tupleStr) {
                    $vals = $this->parseSqlValues($tupleStr);
                    if (! isset($vals[0])) {
                        continue;
                    }
                    $brId = (int) $vals[0];
                    $code = strtoupper(trim((string) ($vals[5] ?? '')));
                    if (! $code) {
                        $code = 'C-'.str_pad((string) $brId, 3, '0', STR_PAD_LEFT);
                    }
                    $nameEn = match ($code) {
                        'CHV' => 'Chhouk Va',
                        'KB' => 'Kamboul',
                        'KSR' => 'Kour Srov',
                        'NR3' => 'National Road 3',
                        default => $code,
                    };
                    $nameKh = trim((string) ($vals[4] ?? '')) ?: $nameEn;
                    $campuses[$brId] = [
                        'id' => $brId,
                        'name_en' => $nameEn,
                        'name_kh' => $nameKh,
                        'code' => $code,
                        'address' => trim((string) ($vals[6] ?? '')) ?: null,
                        'phone' => trim((string) ($vals[8] ?? '')) ?: null,
                        'is_active' => ((int) ($vals[13] ?? 1)) === 1,
                    ];
                }
            }
        }

        // Resolve target campus filter if specified
        $targetCampusId = null;
        if ($targetCampus !== null) {
            $upperCampus = strtoupper(trim($targetCampus));
            foreach ($campuses as $cId => $cData) {
                if ($cData['code'] === $upperCampus || (string) $cId === $targetCampus || strcasecmp($cData['name_en'], $targetCampus) === 0) {
                    $targetCampusId = $cId;
                    break;
                }
            }
            if ($targetCampusId === null) {
                fclose($fp);
                throw new InvalidArgumentException("Specified campus '{$targetCampus}' was not found in backup.");
            }
            if ($progressCallback) {
                $progressCallback("Filtered to Campus: {$campuses[$targetCampusId]['code']} — {$campuses[$targetCampusId]['name_en']}");
            }
        }

        // Step 2: Scan academic years
        rewind($fp);
        if ($progressCallback) {
            $progressCallback('Scanning academic years...');
        }

        $academicYears = [];
        $currentYearId = null;

        while (($line = fgets($fp)) !== false) {
            if (str_starts_with($line, 'INSERT INTO `rms_academicyear` VALUES')) {
                foreach ($this->extractTuples($line) as $tupleStr) {
                    $vals = $this->parseSqlValues($tupleStr);
                    if (isset($vals[0], $vals[1], $vals[2])) {
                        $yId = (int) $vals[0];
                        $label = $vals[1].'-'.$vals[2];
                        $isCurrent = (int) ($vals[4] ?? 0);
                        $academicYears[$yId] = [
                            'id' => $yId,
                            'label' => $label,
                            'is_current' => $isCurrent,
                        ];
                        if ($isCurrent === 1) {
                            $currentYearId = $yId;
                        }
                    }
                }
            }
        }

        // Determine target academic year ID
        $selectedYearId = null;
        $selectedYearLabel = 'Unknown';

        if ($targetYearString !== null) {
            foreach ($academicYears as $yId => $info) {
                if ($info['label'] === $targetYearString || (string) $yId === $targetYearString) {
                    $selectedYearId = $yId;
                    $selectedYearLabel = $info['label'];
                    break;
                }
            }
            if ($selectedYearId === null) {
                fclose($fp);
                throw new InvalidArgumentException("Specified academic year '{$targetYearString}' was not found in backup.");
            }
        } else {
            $selectedYearId = $currentYearId ?? (array_key_last($academicYears) ?: 5);
            $selectedYearLabel = $academicYears[$selectedYearId]['label'] ?? "Year {$selectedYearId}";
        }

        if ($progressCallback) {
            $progressCallback("Target Academic Year: {$selectedYearLabel} (ID: {$selectedYearId})");
        }

        // Step 3: Scan groups for selected academic year
        rewind($fp);
        if ($progressCallback) {
            $progressCallback('Scanning classes / groups...');
        }

        $groups = []; // group_id => ['id' => ..., 'campus_id' => ..., 'name' => ..., 'grade' => ...]
        while (($line = fgets($fp)) !== false) {
            if (str_starts_with($line, 'INSERT INTO `rms_group` VALUES')) {
                foreach ($this->extractTuples($line) as $tupleStr) {
                    $vals = $this->parseSqlValues($tupleStr);
                    // 0: id, 2: branch_id, 3: group_code, 8: academic_year, 12: grade, 17: status
                    if (! isset($vals[0], $vals[3], $vals[8])) {
                        continue;
                    }
                    $groupId = (int) $vals[0];
                    $branchId = (int) ($vals[2] ?? 1);

                    if ($targetCampusId !== null && $branchId !== $targetCampusId) {
                        continue;
                    }

                    $groupCode = trim((string) $vals[3]);
                    $acadYear = (int) $vals[8];
                    $status = (int) ($vals[17] ?? 1);
                    $gradeVal = $vals[12] ?? null;

                    if ($acadYear === $selectedYearId && $status === 1 && $groupCode !== '') {
                        $groups[$groupId] = [
                            'id' => $groupId,
                            'campus_id' => $branchId,
                            'name' => $groupCode,
                            'grade' => $gradeVal ? "Grade {$gradeVal}" : null,
                        ];
                    }
                }
            }
        }

        // Step 4: Scan enrollments for these groups
        rewind($fp);
        if ($progressCallback) {
            $progressCallback('Scanning student enrollments...');
        }

        $studentGroupMap = []; // stu_id => ['group_id' => ..., 'campus_id' => ...]
        while (($line = fgets($fp)) !== false) {
            if (str_starts_with($line, 'INSERT INTO `rms_group_detail_student` VALUES')) {
                foreach ($this->extractTuples($line) as $tupleStr) {
                    $vals = $this->parseSqlValues($tupleStr);
                    // 6: status, 7: stop_type (0 = normal), 16: is_current (1), 27: group_id, 29: stu_id
                    if (! isset($vals[27], $vals[29])) {
                        continue;
                    }
                    $grpId = (int) $vals[27];
                    $stuId = (int) $vals[29];
                    $stopType = (int) ($vals[7] ?? 0);
                    $isCurrent = (int) ($vals[16] ?? 1);
                    $status = (int) ($vals[6] ?? 1);

                    if (isset($groups[$grpId]) && $stopType === 0 && $isCurrent === 1 && $status === 1) {
                        $studentGroupMap[$stuId] = [
                            'group_id' => $grpId,
                            'campus_id' => $groups[$grpId]['campus_id'],
                        ];
                    }
                }
            }
        }

        // Step 5: Scan students
        rewind($fp);
        if ($progressCallback) {
            $progressCallback('Extracting student profile details...');
        }

        $students = [];
        while (($line = fgets($fp)) !== false) {
            if (str_starts_with($line, 'INSERT INTO `rms_student` VALUES')) {
                foreach ($this->extractTuples($line) as $tupleStr) {
                    $vals = $this->parseSqlValues($tupleStr);
                    if (! isset($vals[0])) {
                        continue;
                    }
                    $stuId = (int) $vals[0];

                    if (! isset($studentGroupMap[$stuId])) {
                        continue;
                    }

                    $stuBranchId = (int) ($vals[1] ?? $studentGroupMap[$stuId]['campus_id']);
                    if ($targetCampusId !== null && $stuBranchId !== $targetCampusId) {
                        continue;
                    }

                    $khName = trim((string) ($vals[5] ?? ''));
                    $lastName = trim((string) ($vals[6] ?? ''));
                    $firstName = trim((string) ($vals[7] ?? ''));
                    $enName = trim("{$lastName} {$firstName}");
                    $displayName = $enName !== '' ? $enName : $khName;
                    $stuCode = trim((string) ($vals[9] ?? ''));
                    $sexVal = (int) ($vals[11] ?? 0);
                    $gender = $sexVal === 1 ? 'M' : ($sexVal === 2 ? 'F' : null);
                    $dobVal = $vals[15] ?? null;
                    $dob = ($dobVal && $dobVal !== '0000-00-00') ? substr((string) $dobVal, 0, 10) : null;

                    // Parent contact fallback: Father -> Mother -> Guardian
                    $parentName = trim((string) ($vals[27] ?? '')) ?: (trim((string) ($vals[34] ?? '')) ?: trim((string) ($vals[40] ?? '')));
                    $parentPhone = trim((string) ($vals[32] ?? '')) ?: (trim((string) ($vals[38] ?? '')) ?: trim((string) ($vals[46] ?? '')));
                    $parentEmail = trim((string) ($vals[47] ?? '')) ?: (trim((string) ($vals[18] ?? '')) ?: null);

                    $students[] = [
                        'psis_student_id' => $stuId,
                        'campus_id' => $stuBranchId,
                        'student_code' => $stuCode !== '' ? $stuCode : null,
                        'name' => $displayName,
                        'khmer_name' => $khName !== '' ? $khName : null,
                        'gender' => $gender,
                        'dob' => $dob,
                        'class_psis_id' => $studentGroupMap[$stuId]['group_id'],
                        'parent_name' => $parentName !== '' ? $parentName : null,
                        'parent_phone' => $parentPhone !== '' ? $parentPhone : null,
                        'parent_email' => $parentEmail !== '' ? $parentEmail : null,
                        'is_active' => ((int) ($vals[71] ?? 1)) === 1,
                    ];
                }
            }
        }
        fclose($fp);

        if ($progressCallback) {
            $progressCallback(sprintf('Parsed %d classes and %d enrolled students across %d campuses.', count($groups), count($students), count($campuses)));
        }

        // Summary metrics
        $stats = [
            'academic_year' => $selectedYearLabel,
            'academic_year_id' => $selectedYearId,
            'campus' => $targetCampusId ? $campuses[$targetCampusId]['code'] : 'All (4 Campuses)',
            'campuses_synced' => count($campuses),
            'classes_found' => count($groups),
            'classes_created' => 0,
            'classes_updated' => 0,
            'students_found' => count($students),
            'students_created' => 0,
            'students_updated' => 0,
            'dry_run' => $dryRun,
        ];

        if ($dryRun) {
            return $stats;
        }

        // Step 6: Upsert into Database inside a transaction
        DB::transaction(function () use ($campuses, $groups, $students, &$stats, $progressCallback) {
            if ($progressCallback) {
                $progressCallback('Upserting campuses...');
            }

            foreach ($campuses as $campData) {
                Campus::updateOrCreate(['id' => $campData['id']], $campData);
            }

            if ($progressCallback) {
                $progressCallback('Upserting classes...');
            }

            $classIdMap = []; // psis_group_id => local classes.id

            foreach ($groups as $grp) {
                $classRoom = ClassRoom::where('psis_group_id', $grp['id'])
                    ->orWhere(function ($q) use ($grp) {
                        $q->where('name', $grp['name'])
                            ->where('campus_id', $grp['campus_id']);
                    })
                    ->first();

                if ($classRoom) {
                    $classRoom->update([
                        'campus_id' => $grp['campus_id'],
                        'name' => $grp['name'],
                        'grade' => $grp['grade'] ?? $classRoom->grade,
                        'psis_group_id' => $grp['id'],
                    ]);
                    $stats['classes_updated']++;
                } else {
                    $classRoom = ClassRoom::create([
                        'campus_id' => $grp['campus_id'],
                        'name' => $grp['name'],
                        'grade' => $grp['grade'],
                        'psis_group_id' => $grp['id'],
                    ]);
                    $stats['classes_created']++;
                }
                $classIdMap[$grp['id']] = $classRoom->id;
            }

            if ($progressCallback) {
                $progressCallback('Upserting students in batches...');
            }

            foreach (array_chunk($students, 200) as $chunk) {
                foreach ($chunk as $stuData) {
                    $localClassId = $classIdMap[$stuData['class_psis_id']] ?? null;
                    if (! $localClassId) {
                        continue;
                    }

                    $student = null;
                    if ($stuData['psis_student_id']) {
                        $student = Student::where('psis_student_id', $stuData['psis_student_id'])->first();
                    }
                    if (! $student && $stuData['student_code']) {
                        $student = Student::where('student_code', $stuData['student_code'])->first();
                    }

                    $payload = [
                        'campus_id' => $stuData['campus_id'],
                        'name' => $stuData['name'],
                        'khmer_name' => $stuData['khmer_name'],
                        'student_code' => $stuData['student_code'],
                        'gender' => $stuData['gender'],
                        'dob' => $stuData['dob'],
                        'class_id' => $localClassId,
                        'parent_name' => $stuData['parent_name'],
                        'parent_phone' => $stuData['parent_phone'],
                        'parent_email' => $stuData['parent_email'],
                        'is_active' => $stuData['is_active'],
                        'psis_student_id' => $stuData['psis_student_id'],
                    ];

                    if ($student) {
                        $student->update($payload);
                        $stats['students_updated']++;
                    } else {
                        Student::create($payload);
                        $stats['students_created']++;
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * Synchronize from a local restored MySQL database connection.
     *
     * @param  string  $connection  Name of configured connection (e.g. 'psis_mysql')
     * @param  string|null  $targetYearString  Academic year label or ID
     * @param  string|null  $targetCampus  Campus code or ID
     * @param  bool  $dryRun  Dry run mode
     * @return array<string, mixed>
     */
    public function syncFromDatabase(
        string $connection = 'psis_mysql',
        ?string $targetYearString = null,
        ?string $targetCampus = null,
        bool $dryRun = false
    ): array {
        $db = DB::connection($connection);

        // 1. Campuses
        $rawBranches = $db->table('rms_branch')->get();
        $campuses = [];
        foreach ($rawBranches as $rb) {
            $code = strtoupper(trim((string) ($rb->branch_nameen ?? $rb->abbreviations ?? '')));
            if (! $code) {
                $code = 'C-'.str_pad((string) $rb->br_id, 3, '0', STR_PAD_LEFT);
            }
            $nameEn = match ($code) {
                'CHV' => 'Chhouk Va',
                'KB' => 'Kamboul',
                'KSR' => 'Kour Srov',
                'NR3' => 'National Road 3',
                default => $code,
            };
            $nameKh = trim((string) ($rb->branch_namekh ?? '')) ?: $nameEn;
            $campuses[$rb->br_id] = [
                'id' => $rb->br_id,
                'name_en' => $nameEn,
                'name_kh' => $nameKh,
                'code' => $code,
                'address' => trim((string) ($rb->br_address ?? '')) ?: null,
                'phone' => trim((string) ($rb->branch_tel ?? '')) ?: null,
                'is_active' => ((int) ($rb->status ?? 1)) === 1,
            ];
        }

        // Campus filter
        $targetCampusId = null;
        if ($targetCampus !== null) {
            $upperCampus = strtoupper(trim($targetCampus));
            foreach ($campuses as $cId => $cData) {
                if ($cData['code'] === $upperCampus || (string) $cId === $targetCampus || strcasecmp($cData['name_en'], $targetCampus) === 0) {
                    $targetCampusId = $cId;
                    break;
                }
            }
            if ($targetCampusId === null) {
                throw new InvalidArgumentException("Specified campus '{$targetCampus}' was not found in database.");
            }
        }

        // 2. Academic year
        $yearQuery = $db->table('rms_academicyear');
        if ($targetYearString !== null) {
            $yearQuery->where(function ($q) use ($targetYearString) {
                $q->where(DB::raw("CONCAT(fromYear, '-', toYear)"), $targetYearString)
                    ->orWhere('id', $targetYearString);
            });
        } else {
            $yearQuery->where('isCurrent', 1);
        }
        $academicYear = $yearQuery->first();

        if (! $academicYear) {
            throw new InvalidArgumentException('Target academic year could not be found in database.');
        }

        $yearId = $academicYear->id;
        $yearLabel = "{$academicYear->fromYear}-{$academicYear->toYear}";

        // 3. Groups
        $groupQuery = $db->table('rms_group')
            ->where('academic_year', $yearId)
            ->where('status', 1);

        if ($targetCampusId !== null) {
            $groupQuery->where('branch_id', $targetCampusId);
        }

        $rawGroups = $groupQuery->get();

        $groups = [];
        foreach ($rawGroups as $rg) {
            $groups[$rg->id] = [
                'id' => $rg->id,
                'campus_id' => (int) ($rg->branch_id ?? 1),
                'name' => $rg->group_code,
                'grade' => $rg->grade ? "Grade {$rg->grade}" : null,
            ];
        }

        // 4. Active Enrollments
        $enrollments = $db->table('rms_group_detail_student')
            ->whereIn('group_id', array_keys($groups))
            ->where('stop_type', 0)
            ->where('is_current', 1)
            ->where('status', 1)
            ->pluck('group_id', 'stu_id');

        // 5. Students
        $rawStudents = $db->table('rms_student')
            ->whereIn('stu_id', $enrollments->keys())
            ->get();

        $students = [];
        foreach ($rawStudents as $rs) {
            $khName = trim((string) ($rs->stu_khname ?? ''));
            $lastName = trim((string) ($rs->last_name ?? ''));
            $firstName = trim((string) ($rs->stu_enname ?? ''));
            $enName = trim("{$lastName} {$firstName}");
            $displayName = $enName !== '' ? $enName : $khName;
            $stuCode = trim((string) ($rs->stu_code ?? ''));
            $sexVal = (int) ($rs->sex ?? 0);
            $gender = $sexVal === 1 ? 'M' : ($sexVal === 2 ? 'F' : null);
            $dob = ($rs->dob && $rs->dob !== '0000-00-00') ? substr((string) $rs->dob, 0, 10) : null;

            $parentName = trim((string) ($rs->father_enname ?? '')) ?: (trim((string) ($rs->mother_enname ?? '')) ?: trim((string) ($rs->guardian_enname ?? '')));
            $parentPhone = trim((string) ($rs->father_phone ?? '')) ?: (trim((string) ($rs->mother_phone ?? '')) ?: trim((string) ($rs->guardian_tel ?? '')));
            $parentEmail = trim((string) ($rs->guardian_email ?? '')) ?: (trim((string) ($rs->email ?? '')) ?: null);

            $grpId = $enrollments[$rs->stu_id];
            $students[] = [
                'psis_student_id' => $rs->stu_id,
                'campus_id' => (int) ($rs->branch_id ?? $groups[$grpId]['campus_id']),
                'student_code' => $stuCode !== '' ? $stuCode : null,
                'name' => $displayName,
                'khmer_name' => $khName !== '' ? $khName : null,
                'gender' => $gender,
                'dob' => $dob,
                'class_psis_id' => $grpId,
                'parent_name' => $parentName !== '' ? $parentName : null,
                'parent_phone' => $parentPhone !== '' ? $parentPhone : null,
                'parent_email' => $parentEmail !== '' ? $parentEmail : null,
                'is_active' => ((int) ($rs->status ?? 1)) === 1,
            ];
        }

        $stats = [
            'academic_year' => $yearLabel,
            'academic_year_id' => $yearId,
            'campus' => $targetCampusId ? $campuses[$targetCampusId]['code'] : 'All (4 Campuses)',
            'campuses_synced' => count($campuses),
            'classes_found' => count($groups),
            'classes_created' => 0,
            'classes_updated' => 0,
            'students_found' => count($students),
            'students_created' => 0,
            'students_updated' => 0,
            'dry_run' => $dryRun,
        ];

        if ($dryRun) {
            return $stats;
        }

        // Upsert into Database
        DB::transaction(function () use ($campuses, $groups, $students, &$stats) {
            foreach ($campuses as $campData) {
                Campus::updateOrCreate(['id' => $campData['id']], $campData);
            }

            $classIdMap = [];
            foreach ($groups as $grp) {
                $classRoom = ClassRoom::where('psis_group_id', $grp['id'])
                    ->orWhere(function ($q) use ($grp) {
                        $q->where('name', $grp['name'])
                            ->where('campus_id', $grp['campus_id']);
                    })
                    ->first();

                if ($classRoom) {
                    $classRoom->update([
                        'campus_id' => $grp['campus_id'],
                        'name' => $grp['name'],
                        'grade' => $grp['grade'] ?? $classRoom->grade,
                        'psis_group_id' => $grp['id'],
                    ]);
                    $stats['classes_updated']++;
                } else {
                    $classRoom = ClassRoom::create([
                        'campus_id' => $grp['campus_id'],
                        'name' => $grp['name'],
                        'grade' => $grp['grade'],
                        'psis_group_id' => $grp['id'],
                    ]);
                    $stats['classes_created']++;
                }
                $classIdMap[$grp['id']] = $classRoom->id;
            }

            foreach (array_chunk($students, 200) as $chunk) {
                foreach ($chunk as $stuData) {
                    $localClassId = $classIdMap[$stuData['class_psis_id']] ?? null;
                    if (! $localClassId) {
                        continue;
                    }

                    $student = null;
                    if ($stuData['psis_student_id']) {
                        $student = Student::where('psis_student_id', $stuData['psis_student_id'])->first();
                    }
                    if (! $student && $stuData['student_code']) {
                        $student = Student::where('student_code', $stuData['student_code'])->first();
                    }

                    $payload = [
                        'campus_id' => $stuData['campus_id'],
                        'name' => $stuData['name'],
                        'khmer_name' => $stuData['khmer_name'],
                        'student_code' => $stuData['student_code'],
                        'gender' => $stuData['gender'],
                        'dob' => $stuData['dob'],
                        'class_id' => $localClassId,
                        'parent_name' => $stuData['parent_name'],
                        'parent_phone' => $stuData['parent_phone'],
                        'parent_email' => $stuData['parent_email'],
                        'is_active' => $stuData['is_active'],
                        'psis_student_id' => $stuData['psis_student_id'],
                    ];

                    if ($student) {
                        $student->update($payload);
                        $stats['students_updated']++;
                    } else {
                        Student::create($payload);
                        $stats['students_created']++;
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * Extracts individual tuple strings from an SQL INSERT statement.
     *
     * @return Generator<string>
     */
    public function extractTuples(string $insertLine): Generator
    {
        $len = strlen($insertLine);
        $inQuote = false;
        $escaped = false;
        $tupleStart = -1;

        $valuesPos = stripos($insertLine, 'VALUES');
        if ($valuesPos === false) {
            return;
        }
        $i = $valuesPos + 6;

        while ($i < $len) {
            $ch = $insertLine[$i];

            if ($inQuote) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === "'") {
                    if ($i + 1 < $len && $insertLine[$i + 1] === "'") {
                        $i++;
                    } else {
                        $inQuote = false;
                    }
                }
            } else {
                if ($ch === "'") {
                    $inQuote = true;
                } elseif ($ch === '(') {
                    $tupleStart = $i + 1;
                } elseif ($ch === ')' && $tupleStart !== -1) {
                    yield substr($insertLine, $tupleStart, $i - $tupleStart);
                    $tupleStart = -1;
                }
            }
            $i++;
        }
    }

    /**
     * Parses an individual tuple string into an array of typed PHP values.
     *
     * @return array<int, mixed>
     */
    public function parseSqlValues(string $rowStr): array
    {
        $values = [];
        $len = strlen($rowStr);
        $i = 0;

        while ($i < $len) {
            while ($i < $len && ($rowStr[$i] === ' ' || $rowStr[$i] === ',' || $rowStr[$i] === "\t")) {
                $i++;
            }
            if ($i >= $len) {
                break;
            }

            if ($rowStr[$i] === "'") {
                $i++;
                $str = '';
                while ($i < $len) {
                    if ($rowStr[$i] === '\\' && $i + 1 < $len) {
                        $str .= $rowStr[$i + 1];
                        $i += 2;
                    } elseif ($rowStr[$i] === "'") {
                        if ($i + 1 < $len && $rowStr[$i + 1] === "'") {
                            $str .= "'";
                            $i += 2;
                        } else {
                            $i++;
                            break;
                        }
                    } else {
                        $str .= $rowStr[$i];
                        $i++;
                    }
                }
                $values[] = $str;
            } elseif (substr($rowStr, $i, 4) === 'NULL') {
                $values[] = null;
                $i += 4;
            } else {
                $start = $i;
                while ($i < $len && $rowStr[$i] !== ',' && $rowStr[$i] !== ')') {
                    $i++;
                }
                $val = trim(substr($rowStr, $start, $i - $start));
                $values[] = is_numeric($val) ? (str_contains($val, '.') ? (float) $val : (int) $val) : $val;
            }
        }

        return $values;
    }
}
