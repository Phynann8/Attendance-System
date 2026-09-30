<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Services\ScheduleImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('schedule:import {file : Path to CSV file} {--campus= : Optional campus ID or code to scope class lookup} {--truncate : Overwrite and delete existing schedule slots for classes in the CSV}')]
#[Description('Bulk import class weekly timetables from CSV (Story 39)')]
class ImportClassSchedulesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScheduleImportService $service): int
    {
        $filePath = $this->argument('file');
        $campusOption = $this->option('campus');
        $truncate = (bool) $this->option('truncate');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $campusId = null;
        if ($campusOption) {
            $campus = is_numeric($campusOption)
                ? Campus::find($campusOption)
                : Campus::where('code', strtoupper($campusOption))->orWhere('name_en', $campusOption)->first();

            if (! $campus) {
                $this->error("Campus '{$campusOption}' not found.");

                return self::FAILURE;
            }

            $campusId = $campus->id;
            $this->info("Scoped to campus: {$campus->name_en} ({$campus->code})");
        }

        $this->info("Importing weekly schedules from '{$filePath}'...");

        try {
            $result = $service->importFromCsv(
                filePath: $filePath,
                campusId: $campusId,
                truncate: $truncate
            );

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Rows Processed', $result['total_rows']],
                    ['New Schedules Created', $result['imported']],
                    ['Existing Schedules Updated', $result['updated']],
                    ['Rows Skipped', $result['skipped']],
                ]
            );

            if (! empty($result['errors'])) {
                $this->newLine();
                $this->warn('Warnings and Notices:');
                foreach ($result['errors'] as $err) {
                    $this->line("  - <comment>{$err}</comment>");
                }
            }

            $this->newLine();
            $this->info("Timetable import completed successfully! ({$result['imported']} created, {$result['updated']} updated).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
