<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'zalo_phone')) {
                $table->string('zalo_phone')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('users', 'citizen_id')) {
                $table->string('citizen_id')->nullable()->after('zalo_phone');
            }

            if (!Schema::hasColumn('users', 'start_work_date')) {
                $table->date('start_work_date')->nullable()->after('citizen_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['zalo_phone', 'citizen_id', 'start_work_date'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
