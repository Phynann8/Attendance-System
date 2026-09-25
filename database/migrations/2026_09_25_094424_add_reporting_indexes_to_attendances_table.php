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
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['final_status', 'finalized_at'], 'attendances_final_status_finalized_at_idx');
            $table->index(['student_id', 'final_status'], 'attendances_student_id_final_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_final_status_finalized_at_idx');
            $table->dropIndex('attendances_student_id_final_status_idx');
        });
    }
};
