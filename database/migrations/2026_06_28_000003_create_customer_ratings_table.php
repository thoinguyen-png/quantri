<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('attendance_segment_id')->nullable();
            $table->foreignId('rating_qr_token_id')->nullable()->constrained('rating_qr_tokens')->nullOnDelete();
            $table->date('work_date');
            $table->date('business_date');
            $table->string('rating', 20);
            $table->text('comment')->nullable();
            $table->string('guest_browser_hash', 64);
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('settings_snapshot');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(
                ['employee_id', 'attendance_id', 'guest_browser_hash'],
                'customer_ratings_employee_attendance_browser_unique'
            );
            $table->index(['branch_id', 'business_date']);
            $table->index(['employee_id', 'business_date']);
            $table->index('attendance_segment_id');
            $table->index('rating');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ratings');
    }
};
