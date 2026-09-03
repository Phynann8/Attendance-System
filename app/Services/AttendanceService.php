<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRoom;
use App\Models\Permission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Encapsulates the frozen business workflow:
 *
 *  - Teacher opens a session; approved permissions are pre-locked (Rule 9).
 *  - Teacher marks Present / Absent only (Rules 1-2); locked rows are protected (Rule 10).
 *  - Student Affairs determines LATE from the actual arrival time (Rules 3-4).
 *  - Admin performs the final decision on students that never arrived (Rules 11-14).
 */
class AttendanceService
{
    /**
     * Open a session for a class on a date and pre-create every attendance row.
     * Students with an approved permission are locked with status = permission.
     */
    public static function openSession(ClassRoom $class, User $teacher, ?Carbon $date = null): AttendanceSession
    {
        $dateString = ($date ?? Carbon::today())->format('Y-m-d');

        $alreadyExists = AttendanceSession::query()
            ->where('class_id', $class->id)
            ->whereDate('session_date', $dateString)
            ->exists();

        if ($alreadyExists) {
            throw new RuntimeException('An attendance session already exists for this class on this date.');
        }

        return DB::transaction(function () use ($class, $teacher, $dateString) {
            $session = AttendanceSession::create([
                'class_id' => $class->id,
                'teacher_id' => $teacher->id,
                'session_date' => $dateString,
                'opened_at' => now(),
                'status' => AttendanceSession::STATUS_OPEN,
            ]);

            $approvedPermissionIds = Permission::query()
                ->where('status', Permission::STATUS_APPROVED)
                ->whereDate('attendance_date', $dateString)
                ->where('class_id', $class->id)
                ->pluck('id', 'student_id');

            foreach ($class->activeStudents()->get() as $student) {
                $permissionId = $approvedPermissionIds->get($student->id);

                $attendance = Attendance::create([
                    'attendance_session_id' => $session->id,
                    'student_id' => $student->id,
                    'status' => $permissionId ? Attendance::STATUS_PERMISSION : null,
                    'is_locked' => (bool) $permissionId,
                    'locked_by' => $permissionId ? $teacher->id : null,
                    'locked_at' => $permissionId ? now() : null,
                    'lock_reason' => $permissionId ? 'Approved parent permission' : null,
                    'permission_id' => $permissionId,
                    'case_status' => Attendance::CASE_PENDING,
                    'final_status' => $permissionId ? Attendance::FINAL_EXCUSED : null,
                    'finalized_by' => $permissionId ? $teacher->id : null,
                    'finalized_at' => $permissionId ? now() : null,
                ]);

                AuditService::log(
                    $permissionId ? 'attendance.permission_locked' : 'attendance.record_created',
                    $attendance->id,
                );
            }

            AuditService::log('attendance.session_opened', user: $teacher, details: [
                'class_id' => $class->id,
                'class' => $class->name,
                'date' => $dateString,
            ]);

            return $session->load('attendances.student', 'classRoom');
        });
    }

    /**
     * Save teacher marks. Allowed values: present, absent only (Rules 1-2).
     * Locked (permission) rows can never be changed here (Rule 10).
     */
    public static function saveMarks(AttendanceSession $session, User $teacher, array $statuses): void
    {
        if ($session->status !== AttendanceSession::STATUS_OPEN) {
            throw new RuntimeException('This session is already submitted.');
        }

        DB::transaction(function () use ($session, $teacher, $statuses) {
            $session->attendances()->each(function (Attendance $attendance) use ($teacher, $statuses) {
                if ($attendance->is_locked) {
                    return; // rule 10 - teacher cannot override an approved permission
                }

                $newStatus = $statuses[$attendance->student_id] ?? null;

                if ($newStatus === null) {
                    return; // this student was not part of the (partial) draft save
                }

                if (! in_array($newStatus, [Attendance::STATUS_PRESENT, Attendance::STATUS_ABSENT], true)) {
                    throw new RuntimeException(
                        'Teacher can only mark Present or Absent (permission rows are locked).'
                    );
                }

                if ($attendance->status === $newStatus) {
                    return;
                }

                $oldStatus = $attendance->status;
                $attendance->update([
                    'status' => $newStatus,
                    'marked_by' => $teacher->id,
                    'marked_at' => now(),
                ]);

                AuditService::log('attendance.marked', $attendance->id, details: [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);
            });
        });
    }

    /**
     * Freeze the marks for the day. Every non-locked student must be marked.
     */
    public static function submitSession(AttendanceSession $session, User $teacher): void
    {
        if ($session->status !== AttendanceSession::STATUS_OPEN) {
            throw new RuntimeException('This session was already submitted.');
        }

        $unmarked = $session->attendances()
            ->where('is_locked', false)
            ->whereNull('status')
            ->with('student')
            ->get();

        if ($unmarked->isNotEmpty()) {
            $names = $unmarked->map(fn ($a) => $a->student->name)->implode(', ');
            throw new RuntimeException('Not all students are marked yet: '.$names);
        }

        $session->update([
            'status' => AttendanceSession::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        // Present is final the moment the teacher submits (the day is over for them).
        $session->attendances()
            ->where('is_locked', false)
            ->where('status', Attendance::STATUS_PRESENT)
            ->whereNull('final_status')
            ->update([
                'final_status' => Attendance::FINAL_PRESENT,
                'finalized_by' => $teacher->id,
                'finalized_at' => now(),
            ]);

        AuditService::log('attendance.submitted', user: $teacher, details: [
            'session_id' => $session->id,
            'session_date' => $session->session_date->format('Y-m-d'),
        ]);
    }

    /**
     * Student Affairs verifies a late arrival (Rules 3-4).
     * minutes_late is measured from the moment the teacher submitted attendance.
     */
    public static function recordLateArrival(Attendance $attendance, User $affairs, Carbon $arrivedAt): void
    {
        if ($attendance->status !== Attendance::STATUS_ABSENT) {
            throw new RuntimeException('Only teacher-marked Absent students can be reviewed.');
        }
        if ($attendance->case_status === Attendance::CASE_CLOSED) {
            throw new RuntimeException('This case is already closed.');
        }
        if ($attendance->case_status === Attendance::CASE_ESCALATED) {
            throw new RuntimeException('This case was already escalated to the Admin.');
        }

        $attendanceTime = $attendance->session->submitted_at;

        // Carbon 3 returns signed diffs; compute an unambiguous absolute value.
        $minutesLate = (int) ceil(
            abs($arrivedAt->getTimestamp() - Carbon::parse($attendanceTime)->getTimestamp()) / 60
        );

        $attendance->update([
            'arrived_at' => $arrivedAt,
            'minutes_late' => $minutesLate,
            'case_status' => Attendance::CASE_CLOSED,
            'case_closed_by' => $affairs->id,
            'case_closed_at' => now(),
            'final_status' => Attendance::FINAL_LATE,
            'finalized_by' => $affairs->id,
            'finalized_at' => now(),
        ]);

        AuditService::log('attendance.late_closed', $attendance->id, details: [
            'arrived_at' => $arrivedAt->format('H:i'),
            'minutes_late' => $minutesLate,
        ]);
    }

    /**
     * Student Affairs confirms the student never arrived -> forwarded to Admin.
     */
    public static function escalateNoShow(Attendance $attendance, User $affairs): void
    {
        if ($attendance->status !== Attendance::STATUS_ABSENT) {
            throw new RuntimeException('Only teacher-marked Absent students can be escalated.');
        }
        if ($attendance->case_status === Attendance::CASE_ESCALATED) {
            throw new RuntimeException('This case was already escalated.');
        }
        if ($attendance->case_status === Attendance::CASE_CLOSED) {
            throw new RuntimeException('This case is already closed.');
        }

        $attendance->update([
            'case_status' => Attendance::CASE_ESCALATED,
        ]);

        AuditService::log('attendance.escalated_to_admin', $attendance->id);
    }

    /**
     * Admin final decision: valid parent reason -> permission approved -> excused (Rule 13).
     * Creates the approval so the permission history stays complete.
     */
    public static function finalizeExcused(
        Attendance $attendance,
        User $admin,
        string $requestedBy,
        string $reason,
        ?string $note = null,
    ): Permission {
        if ($attendance->final_status !== null && $attendance->final_status !== Attendance::FINAL_EXCUSED) {
            throw new RuntimeException('This attendance record is already finalized.');
        }

        return DB::transaction(function () use ($attendance, $admin, $requestedBy, $reason, $note) {
            $permission = Permission::create([
                'student_id' => $attendance->student_id,
                'class_id' => $attendance->session->class_id,
                'attendance_date' => $attendance->session->session_date->format('Y-m-d'),
                'requested_by' => $requestedBy,
                'requested_by_type' => Permission::REQUESTED_BY_ADMIN,
                'reason' => $reason,
                'status' => Permission::STATUS_APPROVED,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'admin_note' => $note,
            ]);

            $attendance->update([
                'permission_id' => $permission->id,
                'final_status' => Attendance::FINAL_EXCUSED,
                'finalized_by' => $admin->id,
                'finalized_at' => now(),
                'admin_note' => $note,
            ]);

            AuditService::log('attendance.excused', $attendance->id, $permission->id, details: [
                'reason' => $reason,
            ]);

            return $permission;
        });
    }

    /**
     * Admin final decision: invalid parent reason -> Absent Without Permission (Rule 14).
     */
    public static function finalizeAbsentWithoutPermission(
        Attendance $attendance,
        User $admin,
        ?string $note = null,
    ): void {
        if ($attendance->final_status !== null && $attendance->final_status !== Attendance::FINAL_ABSENT_WITHOUT_PERMISSION) {
            throw new RuntimeException('This attendance record is already finalized.');
        }

        $attendance->update([
            'final_status' => Attendance::FINAL_ABSENT_WITHOUT_PERMISSION,
            'finalized_by' => $admin->id,
            'finalized_at' => now(),
            'admin_note' => $note,
        ]);

        AuditService::log('attendance.absent_without_permission', $attendance->id, details: [
            'note' => $note,
        ]);
    }

    /**
     * Admin: an approved permission already exists for this student/date (section 10
     * of the spec). Apply it as the final excused decision without creating a new one.
     */
    public static function applyExistingPermission(
        Attendance $attendance,
        User $admin,
        Permission $permission,
        ?string $note = null,
    ): void {
        if ($permission->status !== Permission::STATUS_APPROVED) {
            throw new RuntimeException('The permission must be approved to be applied.');
        }
        if ($permission->student_id !== $attendance->student_id) {
            throw new RuntimeException('This permission belongs to a different student.');
        }

        $attendance->update([
            'permission_id' => $permission->id,
            'final_status' => Attendance::FINAL_EXCUSED,
            'finalized_by' => $admin->id,
            'finalized_at' => now(),
            'admin_note' => $note ?? 'Reviewed against existing approved permission.',
        ]);

        AuditService::log('attendance.excused_existing_permission', $attendance->id, $permission->id, details: [
            'note' => $attendance->admin_note,
        ]);
    }

    /**
     * Admin: approve an existing pending permission. If a session is currently open
     * for that class/date, the student row is locked immediately (Rule 9/10).
     */
    public static function approvePermission(Permission $permission, User $admin, ?string $note = null): Permission
    {
        if ($permission->status !== Permission::STATUS_PENDING) {
            throw new RuntimeException('Only pending permissions can be approved.');
        }

        DB::transaction(function () use ($permission, $admin, $note) {
            $permission->update([
                'status' => Permission::STATUS_APPROVED,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'admin_note' => $note ?? $permission->admin_note,
            ]);

            // Lock the matching attendance row in any currently-open session.
            $openSession = AttendanceSession::query()
                ->where('class_id', $permission->class_id)
                ->whereDate('session_date', $permission->attendance_date->format('Y-m-d'))
                ->where('status', AttendanceSession::STATUS_OPEN)
                ->first();

            if ($openSession) {
                $attendance = Attendance::query()
                    ->where('attendance_session_id', $openSession->id)
                    ->where('student_id', $permission->student_id)
                    ->first();

                if ($attendance && ! $attendance->is_locked) {
                    $attendance->update([
                        'status' => Attendance::STATUS_PERMISSION,
                        'is_locked' => true,
                        'locked_by' => $admin->id,
                        'locked_at' => now(),
                        'lock_reason' => 'Approved parent permission',
                        'permission_id' => $permission->id,
                        'final_status' => Attendance::FINAL_EXCUSED,
                        'finalized_by' => $admin->id,
                        'finalized_at' => now(),
                    ]);

                    AuditService::log('attendance.permission_locked', $attendance->id, $permission->id);
                }
            }

            AuditService::log('permission.approved', permissionId: $permission->id, details: [
                'student_id' => $permission->student_id,
                'date' => $permission->attendance_date->format('Y-m-d'),
            ]);
        });

        return $permission->fresh();
    }

    /**
     * Admin: reject a pending permission.
     */
    public static function rejectPermission(Permission $permission, User $admin, ?string $note = null): Permission
    {
        if ($permission->status !== Permission::STATUS_PENDING) {
            throw new RuntimeException('Only pending permissions can be rejected.');
        }

        $permission->update([
            'status' => Permission::STATUS_REJECTED,
            'rejected_by' => $admin->id,
            'rejected_at' => now(),
            'admin_note' => $note ?? $permission->admin_note,
        ]);

        AuditService::log('permission.rejected', permissionId: $permission->id, details: [
            'student_id' => $permission->student_id,
            'date' => $permission->attendance_date->format('Y-m-d'),
        ]);

        return $permission->fresh();
    }
}