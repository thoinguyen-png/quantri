<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_onboarding_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('quick_onboarding_entries', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('expected_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quick_onboarding_entries', function (Blueprint $table) {
            if (Schema::hasColumn('quick_onboarding_entries', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
        });
    }
};
