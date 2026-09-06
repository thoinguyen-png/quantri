<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_supplement_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_supplement_requests', 'shift_segment_id')) {
                $table->foreignId('shift_segment_id')
                    ->nullable()
                    ->after('segment_order')
                    ->constrained('shift_segments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_supplement_requests', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_supplement_requests', 'shift_segment_id')) {
                $table->dropConstrainedForeignId('shift_segment_id');
            }
        });
    }
};
