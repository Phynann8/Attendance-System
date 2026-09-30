<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature('test:smoke')]
#[Description('Run full-cycle multi-role QA smoke & regression suite (Story 44)')]
class SmokeTestCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan>========================================================================</>');
        $this->line('<fg=yellow;options=bold>   ATTENDANCE SYSTEM - MULTI-ROLE QA SMOKE TEST SUITE (Story 44)        </>');
        $this->line('<fg=cyan>========================================================================</>');
        $this->newLine();

        $stages = [
            '[1/6] Super Admin: Campus & Multi-Role User Provisioning',
            '[2/6] Admin: Class Roster & Period Timetable Configuration',
            '[3/6] Teacher: Roll-Call Mark & Submission Confirmation',
            '[4/6] Student Affairs: Unresolved Absence & Late Arrival Review',
            '[5/6] Admin: Multi-Campus Attendance Headcount & Audit Log Trail',
            '[6/6] Parent: Secure Portal & Student Attendance History',
        ];

        foreach ($stages as $stage) {
            $this->line("  <fg=white>{$stage}</> ... <fg=green;options=bold>VERIFYING</>");
        }

        $this->newLine();
        $this->line('Running automated PHPUnit multi-role regression suite...');

        $process = new Process(
            [PHP_BINARY, 'artisan', 'test', '--compact', 'tests/Feature/MultiRoleFullCycleSmokeTest.php'],
            base_path(),
            ['APP_ENV' => 'testing']
        );
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Smoke test suite encountered failures:');
            $this->line($process->getOutput());
            $this->line($process->getErrorOutput());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Lifecycle Stage', 'Role Involved', 'Verification Boundary', 'Status'],
            [
                ['1. Multi-Campus Setup', 'Super Admin', 'Campus creation & User RBAC assignment', 'PASSED'],
                ['2. Class & Schedule', 'Admin', 'Classroom homeroom & Period timetabling', 'PASSED'],
                ['3. Roll-Call Submission', 'Teacher', '1-click mark & confirmation modal submission', 'PASSED'],
                ['4. Absence Resolution', 'Student Affairs', 'Unresolved absence review & late arrival', 'PASSED'],
                ['5. Reporting & Auditing', 'Admin', 'Multi-campus headcount & audit trail', 'PASSED'],
                ['6. Parent Visibility', 'Parent', 'Parent portal & attendance records', 'PASSED'],
            ]
        );

        $this->newLine();
        $this->info('SUCCESS: Full multi-role attendance lifecycle completed end-to-end with 100% green assertions!');
        $this->newLine();

        return self::SUCCESS;
    }
}
