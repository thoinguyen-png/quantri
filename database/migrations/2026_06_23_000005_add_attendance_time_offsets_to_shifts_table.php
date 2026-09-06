<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('shifts', 'checkin_open_before_minutes')) {
                $table->unsignedSmallInteger('checkin_open_before_minutes')->default(60)->after('late_after_minutes');
            }

            if (!Schema::hasColumn('shifts', 'checkin_close_after_minutes')) {
                $table->unsignedSmallInteger('checkin_close_after_minutes')->default(240)->after('checkin_open_before_minutes');
            }

            if (!Schema::hasColumn('shifts', 'checkout_min_after_checkin_minutes')) {
                $table->unsignedSmallInteger('checkout_min_after_checkin_minutes')->default(3)->after('checkin_close_after_minutes');
            }

            if (!Schema::hasColumn('shifts', 'checkout_close_after_shift_end_minutes')) {
                $table->unsignedSmallInteger('checkout_close_after_shift_end_minutes')->nullable()->after('checkout_min_after_checkin_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            foreach ([
                'checkout_close_after_shift_end_minutes',
                'checkout_min_after_checkin_minutes',
                'checkin_close_after_minutes',
                'checkin_open_before_minutes',
            ] as $column) {
                if (Schema::hasColumn('shifts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
