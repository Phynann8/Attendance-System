<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name_en');
            $table->string('name_kh')->nullable();
            $table->string('code', 20)->unique();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('campus_id')->nullable()->after('id')->constrained('campuses')->nullOnDelete();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('campus_id')->nullable()->after('class_id')->constrained('campuses')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('campus_id')->nullable()->after('role_id')->constrained('campuses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropColumn('campus_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropColumn('campus_id');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropColumn('campus_id');
        });

        Schema::dropIfExists('campuses');
    }
};
