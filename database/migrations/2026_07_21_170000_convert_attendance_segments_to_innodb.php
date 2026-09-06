<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_segments')) {
            DB::statement(
                'ALTER TABLE `attendance_segments` ENGINE=InnoDB'
            );

            if (! $this->foreignKeyExists(
                'attendance_segments',
                'attendance_segments_attendance_id_foreign'
            )) {
                DB::statement(
                    'ALTER TABLE `attendance_segments`
                     ADD CONSTRAINT `attendance_segments_attendance_id_foreign`
                     FOREIGN KEY (`attendance_id`)
                     REFERENCES `attendances` (`id`)
                     ON DELETE CASCADE'
                );
            }
        }

        if (Schema::hasTable('shift_segments')) {
            DB::statement(
                'ALTER TABLE `shift_segments` ENGINE=InnoDB'
            );

            if (! $this->foreignKeyExists(
                'shift_segments',
                'shift_segments_shift_id_foreign'
            )) {
                DB::statement(
                    'ALTER TABLE `shift_segments`
                     ADD CONSTRAINT `shift_segments_shift_id_foreign`
                     FOREIGN KEY (`shift_id`)
                     REFERENCES `shifts` (`id`)
                     ON DELETE CASCADE'
                );
            }
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'attendance_checkout_open_before_minutes'],
                [
                    'value' => '120',
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('settings')->updateOrInsert(
                ['key' => 'attendance_checkout_grace_minutes'],
                [
                    'value' => '180',
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('attendance_segments')
            && $this->foreignKeyExists(
                'attendance_segments',
                'attendance_segments_attendance_id_foreign'
            )
        ) {
            DB::statement(
                'ALTER TABLE `attendance_segments`
                 DROP FOREIGN KEY `attendance_segments_attendance_id_foreign`'
            );
        }

        if (
            Schema::hasTable('shift_segments')
            && $this->foreignKeyExists(
                'shift_segments',
                'shift_segments_shift_id_foreign'
            )
        ) {
            DB::statement(
                'ALTER TABLE `shift_segments`
                 DROP FOREIGN KEY `shift_segments_shift_id_foreign`'
            );
        }

        /*
         * Không đổi ngược về MyISAM vì sẽ làm mất
         * transaction và foreign key protection.
         */
    }

    private function foreignKeyExists(
        string $table,
        string $constraint
    ): bool {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};