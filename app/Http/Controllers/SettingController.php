<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\EmploymentStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(EmploymentStatusService $employmentStatus): View
    {
        return view('settings.edit', [
            'probationAutoPromoteEnabled' => $employmentStatus->autoPromoteEnabled(),
            'probationRequiredWorkdays' => $employmentStatus->requiredWorkdays(),
            'attendanceStrictMode' => Setting::getBool('attendance_strict_mode', false),
            'overnightCutoffTime' => Setting::getValue('overnight_cutoff_time', '08:50'),
            'allowPastLeaveRequests' => Setting::getBool('allow_past_leave_requests', false),
            'attendanceSupplementLimitMode' => Setting::getValue('attendance_supplement_limit_mode', 'last_3_days'),
            'maxLeaveRequestDays' => Setting::getInt('max_leave_request_days', 3),
            'shiftAssignmentShowAllShifts' => Setting::getBool('shift_assignment_show_all_shifts', true),
            'customerRatingSettings' => Setting::customerRatingSettingsSnapshot(),
            'ratingRequireActiveAttendance' => Setting::ratingRequiresActiveAttendance(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'probation_auto_promote_enabled' => ['nullable', 'boolean'],
            'probation_required_workdays' => ['required', 'integer', 'min:1', 'max:365'],
            'attendance_strict_mode' => ['nullable', 'boolean'],
            'overnight_cutoff_time' => ['required', 'date_format:H:i'],
            'allow_past_leave_requests' => ['nullable', 'boolean'],
            'attendance_supplement_limit_mode' => ['required', 'in:free,last_3_days'],
            'max_leave_request_days' => ['required', 'integer', 'min:1', 'max:365'],
            'shift_assignment_show_all_shifts' => ['nullable', 'boolean'],
            'good_reward_amount' => ['required', 'integer', 'min:0', 'max:10000000'],
            'max_rewarded_good_per_employee_per_business_date' => ['required', 'integer', 'min:0', 'max:1000'],
            'max_distinct_employees_per_guest_browser_per_branch_per_business_date' => ['required', 'integer', 'min:1', 'max:1000'],
            'rating_require_active_attendance' => ['nullable', 'boolean'],
        ]);
        $actorId = auth()->id();

        Setting::setValue(
            'probation_auto_promote_enabled',
            $request->boolean('probation_auto_promote_enabled') ? 'true' : 'false',
            $actorId
        );
        Setting::setValue('probation_required_workdays', $data['probation_required_workdays'], $actorId);
        Setting::setValue('attendance_strict_mode', $request->boolean('attendance_strict_mode') ? '1' : '0', $actorId);
        Setting::setValue('overnight_cutoff_time', $data['overnight_cutoff_time'], $actorId);
        Setting::setValue('allow_past_leave_requests', $request->boolean('allow_past_leave_requests') ? '1' : '0', $actorId);
        Setting::setValue('attendance_supplement_limit_mode', $data['attendance_supplement_limit_mode'], $actorId);
        Setting::setValue('max_leave_request_days', $data['max_leave_request_days'], $actorId);
        Setting::setValue('shift_assignment_show_all_shifts', $request->boolean('shift_assignment_show_all_shifts') ? '1' : '0', $actorId);
        Setting::setValue('good_reward_amount', $data['good_reward_amount'], $actorId);
        Setting::setValue('max_rewarded_good_per_employee_per_business_date', $data['max_rewarded_good_per_employee_per_business_date'], $actorId);
        Setting::setValue('max_distinct_employees_per_guest_browser_per_branch_per_business_date', $data['max_distinct_employees_per_guest_browser_per_branch_per_business_date'], $actorId);
        Setting::setValue(Setting::RATING_REQUIRE_ACTIVE_ATTENDANCE, $request->boolean('rating_require_active_attendance') ? '1' : '0', $actorId);

        return redirect()
            ->route('settings.edit')
            ->with('success', 'Đã cập nhật cấu hình hệ thống.');
    }
}
