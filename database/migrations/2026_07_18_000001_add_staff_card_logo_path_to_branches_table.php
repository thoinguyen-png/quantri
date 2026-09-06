<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('branches', 'staff_card_logo_path')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('staff_card_logo_path')->nullable()->after('gps_radius');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('branches', 'staff_card_logo_path')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('staff_card_logo_path');
            });
        }
    }
};
