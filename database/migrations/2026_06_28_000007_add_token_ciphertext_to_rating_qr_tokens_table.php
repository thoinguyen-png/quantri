<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rating_qr_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('rating_qr_tokens', 'token_ciphertext')) {
                $table->text('token_ciphertext')->nullable()->after('token_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rating_qr_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('rating_qr_tokens', 'token_ciphertext')) {
                $table->dropColumn('token_ciphertext');
            }
        });
    }
};
