<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('status');
            }

            if (!Schema::hasColumn('attendances', 'locked_at')) {
                $table->dateTime('locked_at')->nullable()->after('is_locked');
            }

            if (!Schema::hasColumn('attendances', 'locked_by')) {
                $table->foreignId('locked_by')
                    ->nullable()
                    ->after('locked_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'locked_by')) {
                $table->dropConstrainedForeignId('locked_by');
            }

            if (Schema::hasColumn('attendances', 'locked_at')) {
                $table->dropColumn('locked_at');
            }

            if (Schema::hasColumn('attendances', 'is_locked')) {
                $table->dropColumn('is_locked');
            }
        });
    }
};
