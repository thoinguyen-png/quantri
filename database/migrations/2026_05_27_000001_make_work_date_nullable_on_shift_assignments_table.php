<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->date('work_date')->nullable()->change();
        });

        $latestIds = DB::table('shift_assignments')
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id')
            ->pluck('id');

        if ($latestIds->isNotEmpty()) {
            DB::table('shift_assignments')
                ->whereNotIn('id', $latestIds)
                ->delete();

            DB::table('shift_assignments')
                ->whereIn('id', $latestIds)
                ->update(['work_date' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->date('work_date')->nullable(false)->change();
        });
    }
};
