<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_rating_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_rating_id')->constrained('customer_ratings')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('amount')->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('reason_code', 80)->nullable();
            $table->json('settings_snapshot');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique('customer_rating_id');
            $table->index(['employee_id', 'business_date', 'status']);
            $table->index(['branch_id', 'business_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_rating_rewards');
    }
};
