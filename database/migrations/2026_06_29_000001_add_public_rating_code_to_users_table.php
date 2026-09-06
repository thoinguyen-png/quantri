<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CHARSET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 8;

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('public_rating_code', self::CODE_LENGTH)->nullable()->after('rating_qr_enabled');
        });

        $existing = DB::table('users')
            ->whereNotNull('public_rating_code')
            ->pluck('public_rating_code')
            ->all();

        $used = array_fill_keys($existing, true);

        DB::table('users')
            ->whereIn('role', ['staff', 'cashier', 'manager'])
            ->whereNull('public_rating_code')
            ->orderBy('id')
            ->select('id')
            ->chunkById(200, function ($users) use (&$used) {
                foreach ($users as $user) {
                    $code = $this->makeUniqueCode($used);
                    $used[$code] = true;

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['public_rating_code' => $code]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('public_rating_code', 'users_public_rating_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_public_rating_code_unique');
            $table->dropColumn('public_rating_code');
        });
    }

    private function makeUniqueCode(array $used): string
    {
        do {
            $code = '';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CHARSET[random_int(0, strlen(self::CHARSET) - 1)];
            }
        } while (isset($used[$code]) || DB::table('users')->where('public_rating_code', $code)->exists());

        return $code;
    }
};
