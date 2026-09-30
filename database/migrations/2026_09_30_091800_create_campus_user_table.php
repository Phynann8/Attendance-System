<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campus_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->primary(['user_id', 'campus_id']);
        });

        // Backfill existing user campus_id relationships into pivot table
        $existing = DB::table('users')->whereNotNull('campus_id')->get(['id', 'campus_id']);
        foreach ($existing as $row) {
            DB::table('campus_user')->insertOrIgnore([
                'user_id' => $row->id,
                'campus_id' => $row->campus_id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campus_user');
    }
};
