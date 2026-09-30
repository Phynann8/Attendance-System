<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Monday, 2 = Tuesday, ... 7 = Sunday
            $table->unsignedInteger('period_number');
            $table->string('subject');
            $table->string('start_time', 10)->nullable(); // e.g. '07:30'
            $table->string('end_time', 10)->nullable();   // e.g. '08:15'
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['class_id', 'day_of_week', 'period_number']);
        });

        Schema::create('schedule_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_schedule_id')->constrained('class_schedules')->cascadeOnDelete();
            $table->date('session_date');
            $table->foreignId('original_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('substitute_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['class_schedule_id', 'session_date']);
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('class_schedule_id')->nullable()->after('class_id')->constrained('class_schedules')->nullOnDelete();
            // Drop old class_id + session_date unique constraint so classes can have multiple period sessions per day
            $table->dropUnique(['class_id', 'session_date']);
            $table->unique(['class_schedule_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropUnique(['class_schedule_id', 'session_date']);
            $table->dropConstrainedForeignId('class_schedule_id');
            $table->unique(['class_id', 'session_date']);
        });

        Schema::dropIfExists('schedule_substitutions');
        Schema::dropIfExists('class_schedules');
    }
};
