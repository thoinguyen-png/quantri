<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'customer_ratings_emp_day_browser_unique';

    public function up(): void
    {
        $duplicate = DB::table('customer_ratings')
            ->select('employee_id', 'business_date', 'guest_browser_hash', DB::raw('COUNT(*) as total'))
            ->groupBy('employee_id', 'business_date', 'guest_browser_hash')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                'Cannot add daily browser rating unique index because duplicate customer_ratings already exist.'
            );
        }

        Schema::table('customer_ratings', function (Blueprint $table) {
            $table->unique(
                ['employee_id', 'business_date', 'guest_browser_hash'],
                self::INDEX_NAME
            );
        });
    }

    public function down(): void
    {
        Schema::table('customer_ratings', function (Blueprint $table) {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
