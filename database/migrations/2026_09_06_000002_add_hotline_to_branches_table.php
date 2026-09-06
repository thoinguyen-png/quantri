<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('branches', 'hotline')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('hotline')->nullable()->after('address');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('branches', 'hotline')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('hotline');
            });
        }
    }
};
