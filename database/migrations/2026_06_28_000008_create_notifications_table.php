<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('type');

            // Không dùng morphs() vì hosting giới hạn index 1000 bytes
            $table->string('notifiable_type', 191);
            $table->unsignedBigInteger('notifiable_id');

            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Index này cũng dùng được khi lọc theo type + id
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at'],
                'notifications_notifiable_read_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};