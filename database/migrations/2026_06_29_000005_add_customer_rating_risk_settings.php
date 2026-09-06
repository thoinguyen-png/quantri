<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $defaults = [
        'rating_risk_ip_employee_window_minutes' => '15',
        'rating_risk_ip_employee_repeat_window_minutes' => '60',
        'rating_risk_ip_employee_repeat_threshold' => '3',
        'rating_risk_many_employees_ip_window_minutes' => '30',
        'rating_risk_many_employees_ip_threshold' => '5',
        'rating_risk_burst_good_window_minutes' => '15',
        'rating_risk_burst_good_threshold' => '5',
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
