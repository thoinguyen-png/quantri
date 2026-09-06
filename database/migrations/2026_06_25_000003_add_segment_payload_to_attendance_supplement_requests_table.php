<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_supplement_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_supplement_requests', 'segment_payload')) {
                $table->json('segment_payload')->nullable()->after('shift_segment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_supplement_requests', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_supplement_requests', 'segment_payload')) {
                $table->dropColumn('segment_payload');
            }
        });
    }
};
