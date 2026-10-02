<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Services\AttendanceService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class AttendanceServiceUnitTest extends TestCase
{
    public function test_calculate_minutes_late_exact_difference(): void
    {
        $submitted = Carbon::parse('2026-10-02 08:00:00');
        $arrived = Carbon::parse('2026-10-02 08:15:00');

        $minutes = AttendanceService::calculateMinutesLate($arrived, $submitted);

        $this->assertSame(15, $minutes);
    }

    public function test_calculate_minutes_late_rounds_up_sub_minute_delays(): void
    {
        $submitted = Carbon::parse('2026-10-02 08:00:00');
        $arrived = Carbon::parse('2026-10-02 08:00:15'); // 15 seconds late

        $minutes = AttendanceService::calculateMinutesLate($arrived, $submitted);

        $this->assertSame(1, $minutes);
    }

    public function test_calculate_minutes_late_zero_seconds_is_zero(): void
    {
        $submitted = Carbon::parse('2026-10-02 08:00:00');
        $arrived = Carbon::parse('2026-10-02 08:00:00');

        $minutes = AttendanceService::calculateMinutesLate($arrived, $submitted);

        $this->assertSame(0, $minutes);
    }

    public function test_calculate_minutes_late_two_hours(): void
    {
        $submitted = Carbon::parse('2026-10-02 07:30:00');
        $arrived = Carbon::parse('2026-10-02 09:30:00');

        $minutes = AttendanceService::calculateMinutesLate($arrived, $submitted);

        $this->assertSame(120, $minutes);
    }

    public function test_attendance_status_constants_are_valid(): void
    {
        $this->assertSame('present', Attendance::STATUS_PRESENT);
        $this->assertSame('absent', Attendance::STATUS_ABSENT);
        $this->assertSame('permission', Attendance::STATUS_PERMISSION);

        $this->assertSame('present', Attendance::FINAL_PRESENT);
        $this->assertSame('late', Attendance::FINAL_LATE);
        $this->assertSame('excused', Attendance::FINAL_EXCUSED);
        $this->assertSame('absent_without_permission', Attendance::FINAL_ABSENT_WITHOUT_PERMISSION);

        $this->assertSame('pending', Attendance::CASE_PENDING);
        $this->assertSame('closed', Attendance::CASE_CLOSED);
        $this->assertSame('escalated', Attendance::CASE_ESCALATED);

        $this->assertSame('open', AttendanceSession::STATUS_OPEN);
        $this->assertSame('submitted', AttendanceSession::STATUS_SUBMITTED);
        $this->assertSame('closed', AttendanceSession::STATUS_CLOSED);
    }
}
