<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'work_date')) {
                $table->date('work_date')->nullable()->after('shift_id');
            }
        });

        DB::statement("
            UPDATE attendances a
            JOIN shift_assignments sa
              ON sa.user_id = a.user_id
             AND sa.shift_id = a.shift_id
             AND DATE(a.checkin_at) = sa.work_date
            SET a.work_date = sa.work_date
            WHERE a.work_date IS NULL
              AND a.checkin_at IS NOT NULL
        ");

        DB::statement("
            UPDATE attendances
            SET work_date = DATE(checkin_at)
            WHERE work_date IS NULL
              AND checkin_at IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'work_date')) {
                $table->dropColumn('work_date');
            }
        });
    }
};
