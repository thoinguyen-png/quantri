<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hired_by')->nullable()->after('hired_at')->constrained('users')->nullOnDelete();
            $table->foreignId('official_by')->nullable()->after('official_at')->constrained('users')->nullOnDelete();
            $table->foreignId('resigned_by')->nullable()->after('resigned_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hired_by');
            $table->dropConstrainedForeignId('official_by');
            $table->dropConstrainedForeignId('resigned_by');
        });
    }
};
