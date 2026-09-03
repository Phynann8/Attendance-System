<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // --- Teacher-entered attendance -----------------------------
            $table->enum('status', ['present', 'absent', 'permission'])->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();

            // --- Permission lock (teacher cannot override) ---------------
            $table->boolean('is_locked')->default(false);
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_reason')->nullable();
            $table->foreignId('permission_id')->nullable()->constrained('permissions')->nullOnDelete();

            // --- Student Affairs case -------------------------------------
            $table->timestamp('arrived_at')->nullable();     // actual arrival time
            $table->integer('minutes_late')->nullable();
            $table->enum('case_status', ['pending', 'closed', 'escalated'])->default('pending');
            $table->foreignId('case_closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('case_closed_at')->nullable();

            // --- Final classification --------------------------------------
            // present | late | excused | absent_without_permission
            $table->enum('final_status', ['present', 'late', 'excused', 'absent_without_permission'])->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->text('admin_note')->nullable();         // parent's verified reason / decision note
            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_id']);
            $table->index(['status', 'case_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};