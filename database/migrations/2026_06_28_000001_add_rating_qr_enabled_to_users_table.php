<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'rating_qr_enabled')) {
                $table->boolean('rating_qr_enabled')->default(true)->after('face_verification_mode');
            }
        });

        DB::table('users')
            ->where('role', 'admin')
            ->update(['rating_qr_enabled' => false]);

        DB::table('users')
            ->whereIn('role', ['staff', 'cashier', 'manager'])
            ->update(['rating_qr_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'rating_qr_enabled')) {
                $table->dropColumn('rating_qr_enabled');
            }
        });
    }
};
