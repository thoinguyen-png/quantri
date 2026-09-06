<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDashboardOvertimeConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_payroll_sum_monthly_overtime_from_minutes_before_rounding(): void
    {
        $this->travelTo(Carbon::parse('2026-07-31 12:00:00'));

        $branch = Branch::create([
            'name' => 'Overtime Consistency Branch',
        ]);

        $employee = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Attendance::create([
            'user_id' => $employee->id,
            'work_date' => '2026-07-01',
            'checkin_at' => '2026-07-01 08:00:00',
            'checkout_at' => '2026-07-01 17:00:00',
            'worked_minutes' => 540,
            'work_day' => 1,
            'overtime_minutes' => 600,
            'overtime_hours' => 10,
            'status' => 'completed',
        ]);

        $this->travel(1)->seconds();

        /*
         * Tổng gốc: 1.050 phút = 17,5 giờ.
         * Nếu làm tròn từng ngày rồi cộng: 17,2 + 0,1 * 4 = 17,6 giờ.
         */
        foreach ([1030, 5, 5, 5, 5] as $index => $overtimeMinutes) {
            $workDate = Carbon::parse('2026-07-01')->addDays($index);

            Attendance::create([
                'user_id' => $employee->id,
                'work_date' => $workDate->toDateString(),
                'checkin_at' => $workDate->copy()->setTime(8, 0),
                'checkout_at' => $workDate->copy()->setTime(17, 0),
                'worked_minutes' => 540,
                'work_day' => 1,
                'overtime_minutes' => $overtimeMinutes,
                'overtime_hours' => round($overtimeMinutes / 60, 2),
                'status' => 'completed',
            ]);
        }

        $dashboardResponse = $this
            ->actingAs($employee)
            ->get(route('attendance.dashboard', [
                'month' => '2026-07',
            ]))
            ->assertOk();

        $payrollResponse = $this
            ->actingAs($admin)
            ->get(route('payrolls.index', [
                'month' => '2026-07',
                'search' => (string) $employee->id,
            ]))
            ->assertOk();

        $dashboardOvertime = (float) $dashboardResponse
            ->viewData('summary')['overtime'];

        $payrollRow = $payrollResponse
            ->viewData('rows')
            ->firstWhere('user_id', $employee->id);

        $payrollOvertime = (float) $payrollRow['metrics']['overtime_hours'];

        $this->assertSame(17.5, $dashboardOvertime);
        $this->assertSame(17.5, $payrollOvertime);
        $this->assertSame($payrollOvertime, $dashboardOvertime);
        $this->assertSame(
            1050,
            (int) $payrollRow['metrics']['overtime_minutes']
        );
    }
}
