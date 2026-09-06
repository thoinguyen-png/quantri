<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'branch_id')) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('shift_id')
                    ->constrained('branches')
                    ->nullOnDelete();

                $table->index('branch_id');
            }
        });

        // Backfill branch_id cho các bản ghi cũ từ users.branch_id
        DB::statement('
            UPDATE attendances a
            JOIN users u ON a.user_id = u.id
            SET a.branch_id = u.branch_id
            WHERE a.branch_id IS NULL AND u.branch_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
};
