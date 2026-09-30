<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsNotificationService
{
    /**
     * Send a raw SMS message via the SMS gateway.
     */
    public static function sendSms(string $phoneNumber, string $message): bool
    {
        $gatewayUrl = config('services.sms.gateway_url', env('SMS_GATEWAY_URL'));

        if (empty($gatewayUrl)) {
            Log::info("[SMS Mock] SMS dispatched to {$phoneNumber}: {$message}");

            return true;
        }

        try {
            $response = Http::timeout(5)->post($gatewayUrl, [
                'to' => $phoneNumber,
                'message' => $message,
                'api_key' => config('services.sms.api_key', env('SMS_API_KEY')),
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("[SMS Failed] Could not deliver SMS to {$phoneNumber}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Send student attendance alert via SMS.
     */
    public static function sendAttendanceAlert(
        Student $student,
        string $status,
        ?string $date = null,
        ?int $minutesLate = null
    ): bool {
        $phone = $student->parent_phone ?? $student->parentUser?->phone;
        if (empty($phone)) {
            return false;
        }

        $dateStr = $date ?? now()->toDateString();
        $className = $student->classRoom?->name ?? 'Class';
        $statusWord = strtoupper($status);

        $msg = "[School Attendance] Notice: Student {$student->name} ({$className}) was marked {$statusWord} on {$dateStr}.";
        if ($minutesLate) {
            $msg .= " Arrived {$minutesLate} minutes late.";
        }
        $msg .= ' Please check Parent Portal or contact school.';

        return self::sendSms($phone, $msg);
    }
}
