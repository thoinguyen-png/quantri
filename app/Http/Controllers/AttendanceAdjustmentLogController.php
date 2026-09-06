<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAdjustmentLog;
use App\Models\User;
use Illuminate\Http\Request;

class AttendanceAdjustmentLogController extends Controller
{
    public function index(Request $request)
    {
        $viewer = auth()->user();

        if (!in_array($viewer->role, ['admin', 'manager'], true)) {
            abort(403);
        }

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $actions = [
            'change_shift' => 'Đổi ca',
            'recalculate_attendance' => 'Tính lại công',
            'update_attendance' => 'Sửa công',
            'delete_attendance' => 'Xóa công',
            'lock_attendance' => 'Khóa công',
            'unlock_attendance' => 'Mở khóa công',
        ];

        $logs = AttendanceAdjustmentLog::with(['attendance', 'user.branch', 'changedBy'])
            ->when($viewer->role === 'manager', function ($query) use ($viewer) {
                $query->whereHas('user', function ($userQuery) use ($viewer) {
                    $userQuery->where('branch_id', $viewer->branch_id);
                });
            })
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('work_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('work_date', '<=', $date))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $users = User::with('branch')
            ->whereIn('role', ['staff', 'manager', 'cashier'])
            ->when($viewer->role === 'manager', function ($query) use ($viewer) {
                $query->where('branch_id', $viewer->branch_id);
            })
            ->orderBy('name')
            ->get();

        return view('attendance_adjustment_logs.index', compact('actions', 'filters', 'logs', 'users'));
    }
}
