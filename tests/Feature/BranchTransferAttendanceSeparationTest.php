<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkHistory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTransferAttendanceSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_transfer_attendance_separation_in_reports_statistics_and_payroll(): void
    {
        Carbon::setTestNow('2026-10-01 00:00:00');

        // 1. Tạo 2 chi nhánh
        $branchA = Branch::create([
            'name' => 'Chi nhánh A',
            'latitude' => 10.8,
            'longitude' => 106.6,
            'gps_radius' => 100,
            'is_active' => true,
        ]);

        $branchB = Branch::create([
            'name' => 'Chi nhánh B',
            'latitude' => 10.9,
            'longitude' => 106.7,
            'gps_radius' => 100,
            'is_active' => true,
        ]);

        // 2. Tạo ca làm việc 8h (08:00 - 16:00)
        $shift = Shift::create([
            'name' => 'Ca sáng',
            'start_at' => '2026-09-01 08:00:00',
            'end_at' => '2026-09-01 16:00:00',
            'late_after_minutes' => 15,
        ]);

        // 3. Tạo nhân sự: Ban đầu ở Branch A, sau đó chuyển sang Branch B từ ngày 11/09/2026
        $employee = User::factory()->create([
            'name' => 'Nguyen Van A',
            'role' => 'staff',
            'branch_id' => $branchB->id, // Hiện tại ở Branch B
            'status' => 'chinh_thuc',
        ]);

        // Ghi nhận lịch sử chuyển công tác từ ngày 11/09/2026
        WorkHistory::create([
            'user_id' => $employee->id,
            'old_branch_id' => $branchA->id,
            'new_branch_id' => $branchB->id,
            'effective_date' => '2026-09-11',
            'note' => 'Điều chuyển công tác sang Chi nhánh B',
        ]);

        // 4. Tạo công từ ngày 01/09 đến 10/09 tại Chi nhánh A (10 ngày)
        for ($day = 1; $day <= 10; $day++) {
            $dateStr = sprintf('2026-09-%02d', $day);
            Attendance::create([
                'user_id' => $employee->id,
                'branch_id' => $branchA->id,
                'shift_id' => $shift->id,
                'work_date' => $dateStr,
                'checkin_at' => Carbon::parse("{$dateStr} 08:00:00"),
                'checkout_at' => Carbon::parse("{$dateStr} 16:00:00"),
                'worked_minutes' => 480,
                'late_minutes' => 0,
                'overtime_minutes' => 0,
                'overtime_hours' => 0.0,
                'work_day' => 1.0,
                'status' => 'completed',
            ]);
        }

        // 5. Tạo công từ ngày 11/09 đến 30/09 tại Chi nhánh B (20 ngày)
        for ($day = 11; $day <= 30; $day++) {
            $dateStr = sprintf('2026-09-%02d', $day);
            Attendance::create([
                'user_id' => $employee->id,
                'branch_id' => $branchB->id,
                'shift_id' => $shift->id,
                'work_date' => $dateStr,
                'checkin_at' => Carbon::parse("{$dateStr} 08:00:00"),
                'checkout_at' => Carbon::parse("{$dateStr} 16:00:00"),
                'worked_minutes' => 480,
                'late_minutes' => 0,
                'overtime_minutes' => 0,
                'overtime_hours' => 0.0,
                'work_day' => 1.0,
                'status' => 'completed',
            ]);
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $managerA = User::factory()->create(['role' => 'manager', 'branch_id' => $branchA->id]);
        $managerB = User::factory()->create(['role' => 'manager', 'branch_id' => $branchB->id]);

        // --- TEST 1: Phía Nhân sự (User) xem đầy đủ 30 ngày công ---
        $this->actingAs($employee);
        $responseHistory = $this->get(route('attendance.history'));
        $responseHistory->assertOk();
        $this->assertCount(30, Attendance::where('user_id', $employee->id)->get());

        // --- TEST 2: Báo cáo công (AttendanceReportController) ---
        // Khi Manager A xem -> Chỉ thấy 10 ngày công của Chi nhánh A
        $this->actingAs($managerA);
        $responseRepA = $this->get(route('attendance-reports.index', ['month' => '2026-09']));
        $responseRepA->assertOk();
        $attendancesA = $responseRepA->viewData('attendances');
        $this->assertSame(10, $attendancesA->total());

        // Khi Manager B xem -> Chỉ thấy 20 ngày công của Chi nhánh B
        $this->actingAs($managerB);
        $responseRepB = $this->get(route('attendance-reports.index', ['month' => '2026-09']));
        $responseRepB->assertOk();
        $attendancesB = $responseRepB->viewData('attendances');
        $this->assertSame(20, $attendancesB->total());

        // --- TEST 3: Thống kê công (AttendanceStatisticController) ---
        // Admin lọc Chi nhánh A
        $this->actingAs($admin);
        $responseStatA = $this->get(route('attendance-statistics.index', [
            'month' => '2026-09',
            'branch_id' => $branchA->id,
        ]));
        $responseStatA->assertOk();
        $rowsA = $responseStatA->viewData('rows');
        $empRowA = $rowsA->firstWhere('user.id', $employee->id);
        $this->assertNotNull($empRowA, 'Nhân sự phải xuất hiện trong bảng thống kê của Chi nhánh A');
        // Kiểm tra tổng công Chi nhánh A là 10 ngày (10 work_units)
        $this->assertEquals(10.0, $empRowA['total_work_units']);
        // Ngày 5/9 phải đủ công, ngày 15/9 phải là nghỉ (thuộc chi nhánh khác)
        $this->assertEquals('full_day', $empRowA['cells']['2026-09-05']['status_key']);
        $this->assertEquals('no_shift', $empRowA['cells']['2026-09-15']['status_key']);

        // Admin lọc Chi nhánh B
        $responseStatB = $this->get(route('attendance-statistics.index', [
            'month' => '2026-09',
            'branch_id' => $branchB->id,
        ]));
        $responseStatB->assertOk();
        $rowsB = $responseStatB->viewData('rows');
        $empRowB = $rowsB->firstWhere('user.id', $employee->id);
        $this->assertNotNull($empRowB, 'Nhân sự phải xuất hiện trong bảng thống kê của Chi nhánh B');
        // Kiểm tra tổng công Chi nhánh B là 20 ngày (20 work_units)
        $this->assertEquals(20.0, $empRowB['total_work_units']);
        // Ngày 5/9 phải là nghỉ, ngày 15/9 phải đủ công
        $this->assertEquals('no_shift', $empRowB['cells']['2026-09-05']['status_key']);
        $this->assertEquals('full_day', $empRowB['cells']['2026-09-15']['status_key']);

        // --- TEST 4: Bảng lương (PayrollController) ---
        // Chi nhánh A chỉ tính lương 10 ngày (10 công)
        $responsePayA = $this->get(route('payrolls.index', [
            'month' => '2026-09',
            'branch_id' => $branchA->id,
        ]));
        $responsePayA->assertOk();
        $payrollRowsA = $responsePayA->viewData('rows');
        $empPayA = collect($payrollRowsA)->firstWhere('user.id', $employee->id);
        $this->assertNotNull($empPayA);
        $this->assertEquals(10.0, $empPayA['metrics']['work_units']);

        // Chi nhánh B chỉ tính lương 20 ngày (20 công)
        $responsePayB = $this->get(route('payrolls.index', [
            'month' => '2026-09',
            'branch_id' => $branchB->id,
        ]));
        $responsePayB->assertOk();
        $payrollRowsB = $responsePayB->viewData('rows');
        $empPayB = collect($payrollRowsB)->firstWhere('user.id', $employee->id);
        $this->assertNotNull($empPayB);
        $this->assertEquals(20.0, $empPayB['metrics']['work_units']);
    }
}
