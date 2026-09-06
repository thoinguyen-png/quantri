<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_onboarding_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('entries_count')->default(0);
            $table->timestamps();

            $table->index(['created_by', 'created_at']);
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('quick_onboarding_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quick_onboarding_batch_id')->constrained('quick_onboarding_batches')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('intended_role')->default('staff');
            $table->string('token', 96)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['quick_onboarding_batch_id', 'id'], 'qoe_batch_id_idx');
            $table->index(['created_by', 'created_at'], 'qoe_creator_created_idx');
            $table->index(['branch_id', 'created_at'], 'qoe_branch_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_onboarding_entries');
        Schema::dropIfExists('quick_onboarding_batches');
    }
};
