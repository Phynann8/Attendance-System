<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->date('session_date');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable(); // the time the teacher locked in the marks
            $table->enum('status', ['open', 'submitted', 'closed'])->default('open');
            $table->timestamps();

            $table->unique(['class_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};