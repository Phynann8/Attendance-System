<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\ClassSchedule;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ScheduleImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClassScheduleController extends Controller
{
    public function store(Request $request, ClassRoom $class): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSuperAdmin(), 403, 'Unauthorized.');

        if (! $user->isSuperAdmin() && $class->campus_id && ! $user->hasCampusAccess($class->campus_id)) {
            abort(403, 'You do not have permission to manage classes from another campus.');
        }

        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'period_number' => ['required', 'integer', 'min:1', 'max:20'],
            'subject' => ['required', 'string', 'max:100'],
            'start_time' => ['nullable', 'string', 'max:10'],
            'end_time' => ['nullable', 'string', 'max:10'],
            'teacher_id' => ['required', 'exists:users,id'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $teacher = User::findOrFail($validated['teacher_id']);
        if (! $teacher->isTeacher() || ! $teacher->is_active) {
            return back()->with('error', 'The selected user is not an active teacher.');
        }

        if ($class->campus_id && ! $teacher->hasCampusAccess($class->campus_id)) {
            return back()->with('error', 'The teacher must belong to the same campus as the class.');
        }

        $exists = ClassSchedule::where('class_id', $class->id)
            ->where('day_of_week', $validated['day_of_week'])
            ->where('period_number', $validated['period_number'])
            ->exists();

        if ($exists) {
            return back()->with('error', "A schedule for Period #{$validated['period_number']} already exists on that day.");
        }

        $schedule = ClassSchedule::create([
            'class_id' => $class->id,
            'day_of_week' => $validated['day_of_week'],
            'period_number' => $validated['period_number'],
            'subject' => $validated['subject'],
            'start_time' => $validated['start_time'] ?: null,
            'end_time' => $validated['end_time'] ?: null,
            'teacher_id' => $teacher->id,
            'is_primary' => $request->boolean('is_primary'),
            'is_active' => true,
        ]);

        AuditService::log('schedule.created', user: $user, details: [
            'class_id' => $class->id,
            'class_name' => $class->name,
            'period_number' => $schedule->period_number,
            'subject' => $schedule->subject,
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->name,
        ]);

        return back()->with('success', "Period #{$schedule->period_number} ({$schedule->subject}) schedule added for {$class->name}.");
    }

    public function destroy(Request $request, ClassRoom $class, ClassSchedule $schedule): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSuperAdmin(), 403, 'Unauthorized.');

        if (! $user->isSuperAdmin() && $class->campus_id && ! $user->hasCampusAccess($class->campus_id)) {
            abort(403, 'You do not have permission to manage classes from another campus.');
        }

        abort_unless($schedule->class_id === $class->id, 404);

        $schedule->delete();

        AuditService::log('schedule.deleted', user: $user, details: [
            'class_id' => $class->id,
            'schedule_id' => $schedule->id,
            'subject' => $schedule->subject,
        ]);

        return back()->with('success', 'Schedule period removed successfully.');
    }

    /**
     * Bulk import class weekly period timetables from a CSV file.
     */
    public function import(Request $request, ScheduleImportService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSuperAdmin(), 403, 'Unauthorized.');

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'truncate' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('csv_file');
        $campusId = $user->activeCampusId();
        $truncate = $request->boolean('truncate');

        try {
            $result = $service->importFromCsv(
                filePath: $file->getPathname(),
                campusId: $campusId,
                truncate: $truncate,
                actor: $user
            );

            $msg = "Timetable import completed: {$result['imported']} created, {$result['updated']} updated.";
            if ($result['skipped'] > 0) {
                $msg .= " ({$result['skipped']} rows skipped).";
            }

            AuditService::log('schedule.bulk_imported', user: $user, details: [
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'filename' => $file->getClientOriginalName(),
            ]);

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', "Import failed: {$e->getMessage()}");
        }
    }

    /**
     * Download sample CSV template for weekly timetables.
     */
    public function downloadTemplate(ScheduleImportService $service)
    {
        $csv = $service->getSampleCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="timetable_template.csv"',
        ]);
    }
}
