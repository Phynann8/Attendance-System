<?php

namespace Tests\Unit;

use App\Services\ScheduleImportService;
use PHPUnit\Framework\TestCase;

class ScheduleImportServiceTest extends TestCase
{
    private ScheduleImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScheduleImportService;
    }

    public function test_it_parses_numeric_day_of_week(): void
    {
        $this->assertSame(1, $this->service->parseDayOfWeek('1'));
        $this->assertSame(2, $this->service->parseDayOfWeek('2'));
        $this->assertSame(5, $this->service->parseDayOfWeek('5'));
        $this->assertSame(6, $this->service->parseDayOfWeek('6'));
        $this->assertSame(7, $this->service->parseDayOfWeek('7'));

        $this->assertNull($this->service->parseDayOfWeek('0'));
        $this->assertNull($this->service->parseDayOfWeek('8'));
        $this->assertNull($this->service->parseDayOfWeek('-1'));
    }

    public function test_it_parses_english_day_names(): void
    {
        $this->assertSame(1, $this->service->parseDayOfWeek('Monday'));
        $this->assertSame(1, $this->service->parseDayOfWeek('monday'));
        $this->assertSame(1, $this->service->parseDayOfWeek('Mon'));
        $this->assertSame(1, $this->service->parseDayOfWeek('m'));

        $this->assertSame(2, $this->service->parseDayOfWeek('Tuesday'));
        $this->assertSame(2, $this->service->parseDayOfWeek('tue'));

        $this->assertSame(3, $this->service->parseDayOfWeek('Wednesday'));
        $this->assertSame(3, $this->service->parseDayOfWeek('wed'));

        $this->assertSame(4, $this->service->parseDayOfWeek('Thursday'));
        $this->assertSame(4, $this->service->parseDayOfWeek('thu'));

        $this->assertSame(5, $this->service->parseDayOfWeek('Friday'));
        $this->assertSame(5, $this->service->parseDayOfWeek('fri'));

        $this->assertSame(6, $this->service->parseDayOfWeek('Saturday'));
        $this->assertSame(6, $this->service->parseDayOfWeek('sat'));

        $this->assertSame(7, $this->service->parseDayOfWeek('Sunday'));
        $this->assertSame(7, $this->service->parseDayOfWeek('sun'));
    }

    public function test_it_returns_null_for_invalid_day_strings(): void
    {
        $this->assertNull($this->service->parseDayOfWeek('Funday'));
        $this->assertNull($this->service->parseDayOfWeek(''));
        $this->assertNull($this->service->parseDayOfWeek('random_text'));
    }

    public function test_it_generates_valid_csv_template(): void
    {
        $template = $this->service->getSampleCsv();

        $this->assertStringContainsString('class_name,day_of_week,period_number,subject,start_time,end_time,teacher_email,is_primary', $template);
        $this->assertStringContainsString('Monday', $template);
        $this->assertStringContainsString('Mathematics', $template);
    }
}
