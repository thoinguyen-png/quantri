<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::create([
            'name' => 'Nốt Nhạc Vui Vip Q12',
            'address' => 'Ho Chi Minh City',
            'latitude' => 10.82625,
            'longitude' => 106.6203044,
            'gps_radius' => 20,
        ]);

        User::create([
            'name' => 'Admin Demo',
            'email' => 'admin@demo.com',
            'password' => Hash::make('123456'),
            'role' => 'admin',
            'branch_id' => $branch->id,
        ]);

        User::create([
            'name' => 'Manager Demo',
            'email' => 'manager@demo.com',
            'password' => Hash::make('123456'),
            'role' => 'manager',
            'branch_id' => $branch->id,
        ]);

        $staff1 = User::create([
            'name' => 'Staff Demo',
            'email' => 'staff@demo.com',
            'password' => Hash::make('123456'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $staff2 = User::create([
            'name' => 'Staff Demo 2',
            'email' => 'staff2@demo.com',
            'password' => Hash::make('123456'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $shiftMorning = Shift::create([
            'name' => 'Ca sáng',
            'start_at' => now()->setTime(10, 0),
            'end_at' => now()->setTime(22, 0),
            'late_after_minutes' => 10,
        ]);

        $shiftNight = Shift::create([
            'name' => 'Ca chiều qua đêm',
            'start_at' => now()->setTime(16, 0),
            'end_at' => now()->addDay()->setTime(4, 0),
            'late_after_minutes' => 10,
        ]);

        ShiftAssignment::create([
            'user_id' => $staff1->id,
            'shift_id' => $shiftMorning->id,
            'work_date' => today(),
        ]);

        ShiftAssignment::create([
            'user_id' => $staff2->id,
            'shift_id' => $shiftNight->id,
            'work_date' => today(),
        ]);
    }
}