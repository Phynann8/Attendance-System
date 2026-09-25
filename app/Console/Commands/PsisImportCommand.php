<?php

namespace App\Console\Commands;

use App\Services\PsisSyncService;
use Illuminate\Console\Command;
use Throwable;

class PsisImportCommand extends Command
{
    protected $signature = 'psis:import
                            {--file= : Path to PSIS SQL backup file}
                            {--source=file : Source to import from (\'file\' or \'db\')}
                            {--db-connection=psis_mysql : Database connection name if source is \'db\'}
                            {--year= : Academic year (e.g. 2026-2027 or 2025-2026)}
                            {--campus= : Optional campus filter (e.g. CHV, KB, KSR, NR3)}
                            {--dry-run : Simulate sync without modifying database}';

    protected $description = 'Import classes and active students from a PSIS SQL backup file or local restored database replica';

    public function handle(PsisSyncService $syncService): int
    {
        $source = (string) $this->option('source');
        $year = $this->option('year') ? (string) $this->option('year') : null;
        $campus = $this->option('campus') ? (string) $this->option('campus') : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();
        $this->info('====================================================');
        $this->info('      PSIS SIS Student & Classroom Synchronizer     ');
        $this->info('====================================================');
        if ($dryRun) {
            $this->warn(' [MODE] DRY RUN (No changes will be written to database)');
        }
        $this->newLine();

        $startTime = microtime(true);

        try {
            if ($source === 'db') {
                $connection = (string) $this->option('db-connection');
                $this->line("Connecting to local database replica: <comment>{$connection}</comment>");

                $stats = $syncService->syncFromDatabase(
                    connection: $connection,
                    targetYearString: $year,
                    targetCampus: $campus,
                    dryRun: $dryRun
                );
            } else {
                $filePath = (string) $this->option('file');

                if (! $filePath) {
                    // Auto-discover PSIS backup in database directory
                    $defaultCandidates = glob(database_path('*psischv*.sql'));
                    if (! empty($defaultCandidates)) {
                        $filePath = $defaultCandidates[0];
                    } else {
                        // Fallback to any .sql in database folder
                        $allSql = glob(database_path('*.sql'));
                        $filePath = $allSql[0] ?? null;
                    }
                }

                if (! $filePath || ! file_exists($filePath)) {
                    $this->error('No PSIS SQL backup file found! Please provide --file=/path/to/backup.sql');

                    return self::FAILURE;
                }

                $this->line("Reading SQL backup: <comment>{$filePath}</comment> (".number_format(filesize($filePath) / 1024 / 1024, 2).' MB)');

                $stats = $syncService->syncFromSqlFile(
                    filePath: $filePath,
                    targetYearString: $year,
                    targetCampus: $campus,
                    dryRun: $dryRun,
                    progressCallback: function (string $message) {
                        $this->line(" <info>></info> {$message}");
                    }
                );
            }

            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info(" Sync completed successfully in {$duration} seconds!");
            $this->newLine();

            $this->table(
                ['Metric', 'Count / Value'],
                [
                    ['Academic Year', $stats['academic_year'] ?? 'N/A'],
                    ['Campus Scope', $stats['campus'] ?? 'All (4 Campuses)'],
                    ['Campuses Synced', $stats['campuses_synced'] ?? 4],
                    ['Classes Found', $stats['classes_found']],
                    ['Classes Created', $stats['classes_created']],
                    ['Classes Updated', $stats['classes_updated']],
                    ['Students Found', $stats['students_found']],
                    ['Students Created', $stats['students_created']],
                    ['Students Updated', $stats['students_updated']],
                    ['Dry Run Mode', $stats['dry_run'] ? 'Yes' : 'No'],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error("Sync failed: {$e->getMessage()}");
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
