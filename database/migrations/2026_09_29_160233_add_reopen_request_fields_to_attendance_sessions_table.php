<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->string('reopen_status')->default('none')->after('status');
            $table->text('reopen_reason')->nullable()->after('reopen_status');
            $table->foreignId('reopen_requested_by')->nullable()->after('reopen_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('reopen_requested_at')->nullable()->after('reopen_requested_by');
            $table->foreignId('reopen_decided_by')->nullable()->after('reopen_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reopen_decided_at')->nullable()->after('reopen_decided_by');
            $table->text('reopen_decision_note')->nullable()->after('reopen_decided_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopen_requested_by');
            $table->dropConstrainedForeignId('reopen_decided_by');
            $table->dropColumn([
                'reopen_status',
                'reopen_reason',
                'reopen_requested_at',
                'reopen_decided_at',
                'reopen_decision_note',
            ]);
        });
    }
};
