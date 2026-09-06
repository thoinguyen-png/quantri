<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('hired_at')->nullable()->after('status');
            $table->date('official_at')->nullable()->after('hired_at');
            $table->date('resigned_at')->nullable()->after('official_at');
            $table->foreignId('status_changed_by')->nullable()->after('resigned_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropColumn(['hired_at', 'official_at', 'resigned_at']);
        });
    }
};
