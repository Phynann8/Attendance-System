<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Services\AuditService;
use App\Services\SmsNotificationService;
use App\Services\TelegramNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAttendanceNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $attendanceId,
        public ?string $overrideStatus = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $attendance = Attendance::with(['student.parentUser', 'student.classRoom', 'student.campus', 'session'])
            ->find($this->attendanceId);

        if (! $attendance || ! $attendance->student) {
            return;
        }

        $student = $attendance->student;
        $status = $this->overrideStatus ?? $attendance->final_status ?? $attendance->status;
        $dateStr = $attendance->session?->session_date?->toDateString() ?? now()->toDateString();

        // Only send alerts for absent or late exceptions
        if (! in_array($status, [Attendance::STATUS_ABSENT, Attendance::FINAL_LATE, Attendance::FINAL_ABSENT_WITHOUT_PERMISSION], true)) {
            return;
        }

        $telegramDelivered = TelegramNotificationService::sendAttendanceAlert(
            $student,
            $status,
            $dateStr,
            $attendance->minutes_late
        );

        $smsDelivered = SmsNotificationService::sendAttendanceAlert(
            $student,
            $status,
            $dateStr,
            $attendance->minutes_late
        );

        Log::info("Attendance notification job executed for student #{$student->id} ({$status}) - Telegram: ".($telegramDelivered ? 'YES' : 'NO').', SMS: '.($smsDelivered ? 'YES' : 'NO'));

        AuditService::log('notification.parent_alert_sent', attendanceId: $attendance->id, details: [
            'student_id' => $student->id,
            'status' => $status,
            'date' => $dateStr,
            'telegram_delivered' => $telegramDelivered,
            'sms_delivered' => $smsDelivered,
        ]);
    }
}
