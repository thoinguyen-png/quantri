<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->firstOrCreate(
            ['key' => 'max_leave_request_days'],
            ['value' => '3']
        );
    }

    public function down(): void
    {
        Setting::query()
            ->where('key', 'max_leave_request_days')
            ->delete();
    }
};
