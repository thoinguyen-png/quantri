<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_rating_reward_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('rewarded_good_count')->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'business_date'], 'customer_rating_reward_counter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_rating_reward_counters');
    }
};
