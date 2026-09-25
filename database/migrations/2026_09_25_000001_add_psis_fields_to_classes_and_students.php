<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedBigInteger('psis_group_id')->nullable()->after('teacher_id')->index();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('student_code')->nullable()->after('id')->index();
            $table->string('khmer_name')->nullable()->after('name');
            $table->string('gender', 10)->nullable()->after('khmer_name');
            $table->date('dob')->nullable()->after('gender');
            $table->unsignedBigInteger('psis_student_id')->nullable()->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['student_code', 'khmer_name', 'gender', 'dob', 'psis_student_id']);
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['psis_group_id']);
        });
    }
};
