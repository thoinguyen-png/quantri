<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_ratings', function (Blueprint $table) {
            $table->string('risk_status', 30)->default('clear')->after('settings_snapshot');
            $table->json('risk_reasons')->nullable()->after('risk_status');
            $table->timestamp('risk_checked_at')->nullable()->after('risk_reasons');

            $table->index(['risk_status', 'business_date'], 'cr_risk_status_date_idx');
            $table->index(['ip_hash', 'employee_id', 'submitted_at'], 'cr_ip_emp_submitted_idx');
            $table->index(['ip_hash', 'submitted_at'], 'cr_ip_submitted_idx');
            $table->index(['employee_id', 'rating', 'submitted_at'], 'cr_emp_rating_submitted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_ratings', function (Blueprint $table) {
            $table->dropIndex('cr_risk_status_date_idx');
            $table->dropIndex('cr_ip_emp_submitted_idx');
            $table->dropIndex('cr_ip_submitted_idx');
            $table->dropIndex('cr_emp_rating_submitted_idx');

            $table->dropColumn([
                'risk_status',
                'risk_reasons',
                'risk_checked_at',
            ]);
        });
    }
};
