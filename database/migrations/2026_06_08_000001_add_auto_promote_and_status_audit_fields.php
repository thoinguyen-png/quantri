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
                ['key' => 'probation_auto_promote_enabled'],
                ['value' => 'true', 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (Schema::hasColumn('users', 'start_work_date')) {
            DB::table('users')
                ->whereNull('start_work_date')
                ->update(['start_work_date' => now()->toDateString()]);

            Schema::table('users', function (Blueprint $table) {
                $table->date('start_work_date')->nullable(false)->change();
            });
        }

        if (Schema::hasTable('work_histories')) {
            Schema::table('work_histories', function (Blueprint $table) {
                if (!Schema::hasColumn('work_histories', 'old_status')) {
                    $table->string('old_status')->nullable()->after('new_role');
                }

                if (!Schema::hasColumn('work_histories', 'new_status')) {
                    $table->string('new_status')->nullable()->after('old_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_histories')) {
            Schema::table('work_histories', function (Blueprint $table) {
                foreach (['old_status', 'new_status'] as $column) {
                    if (Schema::hasColumn('work_histories', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('key', 'probation_auto_promote_enabled')
                ->delete();
        }
    }
};
