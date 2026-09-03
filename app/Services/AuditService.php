<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Writes a row into attendance_logs for every meaningful action (Rule 15).
 */
class AuditService
{
    public static function log(
        string $action,
        ?int $attendanceId = null,
        ?int $permissionId = null,
        ?User $user = null,
        array|string|null $details = null,
    ): AttendanceLog {
        $actor = $user ?? Auth::user();

        return AttendanceLog::create([
            'attendance_id' => $attendanceId,
            'permission_id' => $permissionId,
            'user_id' => $actor instanceof Authenticatable ? $actor->getAuthIdentifier() : null,
            'action' => $action,
            'details' => is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
        ]);
    }
}