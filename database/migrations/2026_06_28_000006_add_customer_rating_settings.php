<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $defaults = [
        'good_reward_amount' => '0',
        'max_rewarded_good_per_employee_per_business_date' => '3',
        'max_distinct_employees_per_guest_browser_per_branch_per_business_date' => '3',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->defaults as $key => $value) {
            if (!DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->defaults as $key => $value) {
            DB::table('settings')
                ->where('key', $key)
                ->where('value', $value)
                ->delete();
        }
    }
};
