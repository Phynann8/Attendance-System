<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->date('attendance_date');            // the class day the permission applies to
            $table->string('requested_by');             // e.g. "Sok Dara (parent)"
            $table->enum('requested_by_type', ['parent', 'admin'])->default('parent');
            $table->string('reason');                   // e.g. "Medical appointment"
            $table->string('evidence_path')->nullable(); // uploaded supporting document
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'attendance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
