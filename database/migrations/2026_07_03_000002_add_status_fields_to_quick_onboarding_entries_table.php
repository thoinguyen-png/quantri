<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_onboarding_entries', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->after('token');
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('sent_at');
            $table->foreignId('completed_user_id')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->json('submitted_payload')->nullable()->after('completed_user_id');

            $table->index(['quick_onboarding_batch_id', 'status'], 'qoe_batch_status_idx');
            $table->index(['completed_user_id', 'completed_at'], 'qoe_completed_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('quick_onboarding_entries', function (Blueprint $table) {
            $table->dropIndex('qoe_batch_status_idx');
            $table->dropIndex('qoe_completed_user_idx');
            $table->dropForeign(['completed_user_id']);
            $table->dropColumn(['status', 'sent_at', 'completed_at', 'completed_user_id', 'submitted_payload']);
        });
    }
};
