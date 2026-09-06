<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'employment_status')) {
                $table->string('employment_status')
                    ->default('probation')
                    ->after('status')
                    ->index();
            }
        });

        DB::table('users')
            ->where('status', 'chinh_thuc')
            ->update(['employment_status' => 'official']);

        DB::table('users')
            ->where(function ($query) {
                $query->whereNull('employment_status')
                    ->orWhere('employment_status', '');
            })
            ->update(['employment_status' => 'probation']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'employment_status')) {
                $table->dropColumn('employment_status');
            }
        });
    }
};
