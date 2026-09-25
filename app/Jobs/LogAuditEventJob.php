<?php

namespace App\Jobs;

use App\Models\AttendanceLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class LogAuditEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $action,
        public ?int $attendanceId = null,
        public ?int $permissionId = null,
        public ?int $userId = null,
        public ?string $details = null,
    ) {}

    public function handle(): AttendanceLog
    {
        return AttendanceLog::create([
            'attendance_id' => $this->attendanceId,
            'permission_id' => $this->permissionId,
            'user_id' => $this->userId,
            'action' => $this->action,
            'details' => $this->details,
        ]);
    }
}
