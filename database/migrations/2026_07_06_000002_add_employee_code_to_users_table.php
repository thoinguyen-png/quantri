<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'employee_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('employee_code', 4)->nullable()->after('branch_id');
            });
        }

        $usersByBranch = DB::table('users')
            ->select(['id', 'branch_id'])
            ->whereNotNull('branch_id')
            ->orderBy('branch_id')
            ->orderBy('id')
            ->get()
            ->groupBy('branch_id');

        foreach ($usersByBranch as $users) {
            $number = 1;

            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'employee_code' => str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    ]);

                $number++;
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['branch_id', 'employee_code'], 'users_branch_employee_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_branch_employee_code_unique');
            $table->dropColumn('employee_code');
        });
    }
};
