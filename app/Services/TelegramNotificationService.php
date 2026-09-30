<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Send a raw Telegram message via the Bot API.
     */
    public static function sendMessage(string $chatId, string $message, string $parseMode = 'HTML'): bool
    {
        $botToken = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));

        if (empty($botToken)) {
            Log::info("[Telegram Mock] Message dispatched to chat {$chatId}: {$message}");

            return true;
        }

        try {
            $response = Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("[Telegram Failed] Could not deliver message to {$chatId}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Send formatted student attendance status alert to parent's Telegram chat.
     */
    public static function sendAttendanceAlert(
        Student $student,
        string $status,
        ?string $date = null,
        ?int $minutesLate = null,
        ?string $details = null
    ): bool {
        $chatId = $student->telegram_chat_id ?? $student->parentUser?->telegram_chat_id;
        if (empty($chatId)) {
            return false;
        }

        $dateStr = $date ?? now()->toDateString();
        $className = $student->classRoom?->name ?? 'General';
        $campus = $student->campus?->code ?? 'Main';

        $statusTitle = match (strtolower($status)) {
            'absent' => '❌ ABSENT / អវត្តមាន',
            'late' => '⏰ LATE / មកយឺត'.($minutesLate ? " ({$minutesLate} mn)" : ''),
            'permission' => '📝 PERMISSION APPROVED / មានច្បាប់អនុញ្ញាត',
            default => strtoupper($status),
        };

        $msg = "<b>🏛️ School Attendance Alert / ការជូនដំណឹងវត្តមានសាលា</b>\n\n";
        $msg .= '<b>Student / សិស្ស:</b> '.htmlspecialchars($student->name)."\n";
        $msg .= '<b>Class / ថ្នាក់:</b> '.htmlspecialchars($className)."\n";
        $msg .= '<b>Campus / សាខា:</b> '.htmlspecialchars($campus)."\n";
        $msg .= "<b>Date / កាលបរិច្ឆេទ:</b> {$dateStr}\n";
        $msg .= "<b>Status / ស្ថានភាព:</b> {$statusTitle}\n";

        if (! empty($details)) {
            $msg .= '<b>Detail / ព័ត៌មានបន្ថែម:</b> '.htmlspecialchars($details)."\n";
        }

        $msg .= "\n<i>If this status was unexpected, please contact Student Affairs or submit a request via the Parent Portal.</i>";

        return self::sendMessage($chatId, $msg);
    }
}
