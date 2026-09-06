<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_onboarding_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('quick_onboarding_entries', 'demo_code')) {
                $table->string('demo_code', 3)->nullable()->after('id');
            }

            if (!Schema::hasColumn('quick_onboarding_entries', 'expected_name')) {
                $table->string('expected_name')->nullable()->after('demo_code');
            }

            if (!Schema::hasColumn('quick_onboarding_entries', 'submitted_payload')) {
                $table->json('submitted_payload')->nullable()->after('completed_user_id');
            }

            $table->unique(['quick_onboarding_batch_id', 'demo_code'], 'qoe_batch_demo_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('quick_onboarding_entries', function (Blueprint $table) {
            $table->dropUnique('qoe_batch_demo_code_unique');
            $table->dropColumn(['demo_code', 'expected_name']);
        });
    }
};
