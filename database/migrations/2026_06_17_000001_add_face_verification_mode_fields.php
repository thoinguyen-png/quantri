<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'face_verification_mode')) {
                $table->enum('face_verification_mode', ['normal', 'priority'])->default('normal')->after('face_descriptor');
            }
        });

        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'face_verification_mode')) {
                $table->string('face_verification_mode', 20)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'face_verification_mode')) {
                $table->dropColumn('face_verification_mode');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'face_verification_mode')) {
                $table->dropColumn('face_verification_mode');
            }
        });
    }
};
