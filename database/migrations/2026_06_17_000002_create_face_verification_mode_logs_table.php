<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('face_verification_mode_logs')) {
            return;
        }

        Schema::create('face_verification_mode_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('bulk_filter_json')->nullable();
            $table->string('old_mode', 20)->nullable();
            $table->string('new_mode', 20);
            $table->string('action', 100)->default('update_face_verification_mode');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_verification_mode_logs');
    }
};
