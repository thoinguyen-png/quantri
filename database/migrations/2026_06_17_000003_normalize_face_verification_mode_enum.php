<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'face_verification_mode')) {
            return;
        }

        DB::table('users')
            ->whereNull('face_verification_mode')
            ->orWhereNotIn('face_verification_mode', ['normal', 'priority'])
            ->update(['face_verification_mode' => 'normal']);

        DB::statement("ALTER TABLE users MODIFY COLUMN face_verification_mode ENUM('normal', 'priority') NOT NULL DEFAULT 'normal'");
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'face_verification_mode')) {
            DB::statement("ALTER TABLE users MODIFY COLUMN face_verification_mode VARCHAR(20) NOT NULL DEFAULT 'normal'");
        }
    }
};
