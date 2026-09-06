<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $filters = [
            'search' => trim((string) request('search', '')),
            'branch_id' => (string) request('branch_id', ''),
            'role' => (string) request('role', ''),
            'status' => (string) request('status', ''),
            'date_from' => (string) request('date_from', ''),
            'date_to' => (string) request('date_to', ''),
            'reviewed_by' => (string) request('reviewed_by', ''),
            'created_from' => (string) request('created_from', ''),
            'created_to' => (string) request('created_to', ''),
        ];

        $leaveRequests = LeaveRequest::with(['user.branch', 'reviewer', 'creator', 'updater'])
            ->when(in_array($user->role, ['staff', 'cashier'], true), function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->when($user->role === 'manager', function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereHas('user', function ($userQuery) use ($user) {
                            $userQuery
                                ->whereIn('role', ['staff', 'cashier'])
                                ->where('branch_id', $user->branch_id);
                    });
                });
            })
            ->when($filters['search'] !== '', fn ($query) => $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$filters['search']}%")))
            ->when($filters['branch_id'] !== '', fn ($query) => $query->whereHas('user', fn ($q) => $q->where('branch_id', $filters['branch_id'])))
            ->when(in_array($filters['role'], ['admin', 'manager', 'staff', 'cashier'], true), fn ($query) => $query->whereHas('user', fn ($q) => $q->where('role', $filters['role'])))
            ->when(in_array($filters['status'], ['pending', 'approved', 'rejected'], true), fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('start_date', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('end_date', '<=', $filters['date_to']))
            ->when($filters['reviewed_by'] !== '', fn ($query) => $query->where('reviewed_by', $filters['reviewed_by']))
            ->when($filters['created_from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['created_from']))
            ->when($filters['created_to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['created_to']))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $branches = \App\Models\Branch::active()
            ->when($user->role === 'manager', fn ($query) => $query->where('id', $user->branch_id))
            ->orderBy('name')
            ->get();
        $reviewers = \App\Models\User::whereIn('role', ['admin', 'manager'])->orderBy('name')->get();

        return view('leave_requests.index', compact('branches', 'filters', 'leaveRequests', 'reviewers'));
    }

    public function create()
    {
        return view('leave_requests.create', [
            'allowPastLeaveRequests' => Setting::getBool('allow_past_leave_requests', false),
            'maxLeaveRequestDays' => Setting::getInt('max_leave_request_days', 3),
        ]);
    }

    public function store(Request $request)
    {
        $allowPastLeaveRequests = Setting::getBool('allow_past_leave_requests', false);
        $startDateRules = ['required', 'date'];

        if (!$allowPastLeaveRequests) {
            $startDateRules[] = 'after_or_equal:today';
        }

        $data = $request->validate([
            'start_date' => $startDateRules,
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'start_date.after_or_equal' => 'Chỉ được nộp đơn nghỉ phép/off từ hôm nay trở đi.',
        ]);

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $maxLeaveRequestDays = Setting::getInt('max_leave_request_days', 3);

        if ($totalDays > $maxLeaveRequestDays) {
            return back()
                ->withInput()
                ->withErrors([
                    'end_date' => "Bạn chỉ được gửi đơn nghỉ tối đa {$maxLeaveRequestDays} ngày/lần.",
                ]);
        }

        LeaveRequest::create([
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'user_id' => auth()->id(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => $totalDays,
            'leave_type' => 'nghi_phep',
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        return redirect()
            ->route('leave-requests.index')
            ->with('success', 'Đã gửi đơn xin nghỉ.');
    }

    public function adminCreate()
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $users = User::with('branch')
            ->whereIn('role', ['manager', 'staff', 'cashier'])
            ->orderBy('name')
            ->get();

        return view('leave_requests.admin_create', [
            'users' => $users,
        ]);
    }

    public function adminStore(Request $request)
    {
        $admin = auth()->user();

        abort_unless($admin?->role === 'admin', 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $user = User::query()
            ->whereKey((int) $data['user_id'])
            ->whereIn('role', ['manager', 'staff', 'cashier'])
            ->firstOrFail();

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->startOfDay();
        $totalDays = $startDate->diffInDays($endDate) + 1;

        $hasOverlap = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query
                    ->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($coveringQuery) use ($startDate, $endDate) {
                        $coveringQuery
                            ->whereDate('start_date', '<=', $startDate->toDateString())
                            ->whereDate('end_date', '>=', $endDate->toDateString());
                    });
            })
            ->exists();

        if ($hasOverlap) {
            return back()
                ->withInput()
                ->withErrors([
                    'start_date' => 'Nhân sự đã có đơn nghỉ đang chờ duyệt hoặc đã được duyệt trùng khoảng ngày này.',
                ]);
        }

        LeaveRequest::create([
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'user_id' => $user->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => $totalDays,
            'leave_type' => 'nghi_phep',
            'reason' => $data['reason'],
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'review_note' => 'Admin bổ sung phép trực tiếp.',
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('leave-requests.index')
            ->with('success', "Đã bổ sung nghỉ phép cho {$user->name}.");
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $leaveRequest->refresh();

        $this->authorizeReview($leaveRequest);

        if ($leaveRequest->status !== 'pending') {
            return back()->withErrors(['request' => 'Đơn này đã được xử lý.']);
        }

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $leaveRequest->update([
            'reviewed_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'status' => 'approved',
            'review_note' => $data['review_note'] ?? null,
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('leave-requests.index')
            ->with('success', 'Đã duyệt đơn xin nghỉ.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $leaveRequest->refresh();

        $this->authorizeReview($leaveRequest);

        if ($leaveRequest->status !== 'pending') {
            return back()->withErrors(['request' => 'Đơn này đã được xử lý.']);
        }

        $data = $request->validate([
            'review_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $leaveRequest->update([
            'reviewed_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'status' => 'rejected',
            'review_note' => $data['review_note'],
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('leave-requests.index')
            ->with('success', 'Đã từ chối đơn xin nghỉ.');
    }

    private function authorizeReview(LeaveRequest $leaveRequest): void
    {
        $viewer = auth()->user();
        $owner = $leaveRequest->user;

        if ($viewer->role === 'admin') {
            return;
        }

        if (
            $viewer->role === 'manager' &&
            in_array($owner->role, ['staff', 'cashier'], true) &&
            $owner->branch_id === $viewer->branch_id
        ) {
            return;
        }

        abort(403);
    }
}