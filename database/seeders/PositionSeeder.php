<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Có thể chạy độc lập: php artisan db:seed --class=PositionSeeder
     */
    public function run(): void
    {
        $positions = [
            [
                'name' => 'Tổng quản lý',
                'name_en' => 'General Manager',
                'code' => 'tong_quan_ly',
                'system_role' => Position::ROLE_MANAGER,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Phó quản lý',
                'name_en' => 'Deputy Manager',
                'code' => 'pho_quan_ly',
                'system_role' => Position::ROLE_MANAGER,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Thu ngân',
                'name_en' => 'Cashier',
                'code' => 'thu_ngan',
                'system_role' => Position::ROLE_CASHIER,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Tổ trưởng',
                'name_en' => 'Manager',
                'code' => 'to_truong',
                'system_role' => Position::ROLE_MANAGER,
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Tổ phó',
                'name_en' => 'Leader',
                'code' => 'to_pho',
                'system_role' => Position::ROLE_STAFF,
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Phục vụ',
                'name_en' => 'Waiter',
                'code' => 'phuc_vu',
                'system_role' => Position::ROLE_STAFF,
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Bếp',
                'name_en' => 'Chef',
                'code' => 'bep',
                'system_role' => Position::ROLE_STAFF,
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Kho',
                'name_en' => 'Storekeeper',
                'code' => 'kho',
                'system_role' => Position::ROLE_STAFF,
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'Tạp vụ',
                'name_en' => 'Cleaning Staff',
                'code' => 'tap_vu',
                'system_role' => Position::ROLE_STAFF,
                'sort_order' => 9,
                'is_active' => true,
            ],
            [
                'name' => 'Bảo vệ',
                'name_en' => 'Security',
                'code' => 'bao_ve',
                'system_role' => Position::ROLE_STAFF,
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Quản lý CSKH',
                'name_en' => 'PR Manager',
                'code' => 'quan_ly_cskh',
                'system_role' => Position::ROLE_STAFF,
                'sort_order' => 11,
                'is_active' => true,
            ],
            [
                'name' => 'Giám đốc',
                'name_en' => 'Director',
                'code' => 'giam_doc',
                'system_role' => Position::ROLE_ADMIN,
                'sort_order' => 12,
                'is_active' => true,
            ],
            [
                'name' => 'Giám đốc vùng',
                'name_en' => 'Regional Director',
                'code' => 'giam_doc_vung',
                'system_role' => Position::ROLE_ADMIN,
                'sort_order' => 13,
                'is_active' => true,
            ],
            [
                'name' => 'Trưởng phòng HCNS',
                'name_en' => 'HR & Admin Manager',
                'code' => 'truong_phong_hcns',
                'system_role' => Position::ROLE_ADMIN,
                'sort_order' => 14,
                'is_active' => true,
            ],
            [
                'name' => 'Kế toán',
                'name_en' => 'Accountant',
                'code' => 'ke_toan',
                'system_role' => Position::ROLE_STAFF,
                'sort_order' => 15,
                'is_active' => true,
            ],
            [
                'name' => 'IT',
                'name_en' => 'IT Specialist',
                'code' => 'it',
                'system_role' => Position::ROLE_STAFF,
                'sort_order' => 16,
                'is_active' => true,
            ],
            [
                'name' => 'Quản trị viên',
                'name_en' => 'Administrator',
                'code' => 'admin',
                'system_role' => Position::ROLE_ADMIN,
                'sort_order' => 17,
                'is_active' => true,
            ],
        ];

        $createdPositions = [];
        foreach ($positions as $pos) {
            $createdPositions[$pos['code']] = Position::updateOrCreate(
                ['code' => $pos['code']],
                $pos
            );
        }

        // Tự động map position_id cho các user hiện có nếu chưa có position_id
        $adminPosId = $createdPositions['admin']->id ?? null;
        $managerPosId = $createdPositions['tong_quan_ly']->id ?? null;
        $cashierPosId = $createdPositions['thu_ngan']->id ?? null;
        $staffPosId = $createdPositions['phuc_vu']->id ?? null;

        if ($adminPosId) {
            User::whereNull('position_id')->where('role', 'admin')->update(['position_id' => $adminPosId]);
        }
        if ($managerPosId) {
            User::whereNull('position_id')->where('role', 'manager')->update(['position_id' => $managerPosId]);
        }
        if ($cashierPosId) {
            User::whereNull('position_id')->where('role', 'cashier')->update(['position_id' => $cashierPosId]);
        }
        if ($staffPosId) {
            User::whereNull('position_id')->where('role', 'staff')->update(['position_id' => $staffPosId]);
        }
    }
}
