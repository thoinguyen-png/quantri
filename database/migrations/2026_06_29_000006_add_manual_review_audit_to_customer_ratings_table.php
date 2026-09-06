<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_ratings', function (Blueprint $table) {
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('risk_checked_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');

            $table->index(['reviewed_by', 'reviewed_at'], 'cr_reviewed_by_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_ratings', function (Blueprint $table) {
            $table->dropIndex('cr_reviewed_by_at_idx');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'reviewed_at',
                'review_note',
            ]);
        });
    }
};
