<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_supplement_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('segment_order')->nullable()->after('shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_supplement_requests', function (Blueprint $table) {
            $table->dropColumn('segment_order');
        });
    }
};
