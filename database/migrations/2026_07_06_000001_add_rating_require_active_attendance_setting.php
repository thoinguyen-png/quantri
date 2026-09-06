<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'rating_require_active_attendance'],
                [
                    'value' => '1',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('customer_ratings') && Schema::hasColumn('customer_ratings', 'attendance_id')) {
            Schema::table('customer_ratings', function (Blueprint $table) {
                $table->unsignedBigInteger('attendance_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('customer_rating_rewards') && Schema::hasColumn('customer_rating_rewards', 'attendance_id')) {
            Schema::table('customer_rating_rewards', function (Blueprint $table) {
                $table->unsignedBigInteger('attendance_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'rating_require_active_attendance')->delete();
        }

        if (
            Schema::hasTable('customer_ratings')
            && Schema::hasColumn('customer_ratings', 'attendance_id')
            && !DB::table('customer_ratings')->whereNull('attendance_id')->exists()
        ) {
            Schema::table('customer_ratings', function (Blueprint $table) {
                $table->unsignedBigInteger('attendance_id')->nullable(false)->change();
            });
        }

        if (
            Schema::hasTable('customer_rating_rewards')
            && Schema::hasColumn('customer_rating_rewards', 'attendance_id')
            && !DB::table('customer_rating_rewards')->whereNull('attendance_id')->exists()
        ) {
            Schema::table('customer_rating_rewards', function (Blueprint $table) {
                $table->unsignedBigInteger('attendance_id')->nullable(false)->change();
            });
        }
    }
};
