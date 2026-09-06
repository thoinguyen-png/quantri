<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings') || Schema::hasColumn('settings', 'updated_by')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('updated_by')
                ->nullable()
                ->after('value')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings') || !Schema::hasColumn('settings', 'updated_by')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
