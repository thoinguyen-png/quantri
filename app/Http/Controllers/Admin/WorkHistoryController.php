<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Models\WorkHistory;
use Illuminate\Http\Request;

class WorkHistoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $histories = $this->baseQuery($filters)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.work_histories.index', [
            'branches' => Branch::orderBy('name')->get(),
            'filters' => $filters,
            'histories' => $histories,
            'users' => User::whereIn('role', ['manager', 'staff', 'cashier'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function user(Request $request, User $user)
    {
        $filters = $this->filters($request);
        $histories = $this->baseQuery($filters)
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.work_histories.user', [
            'branches' => Branch::orderBy('name')->get(),
            'filters' => $filters,
            'histories' => $histories,
            'user' => $user->loadMissing('branch'),
        ]);
    }

    private function baseQuery(array $filters)
    {
        return WorkHistory::query()
            ->with(['user.branch', 'oldBranch', 'newBranch', 'changedBy'])
            ->when($filters['user_id'], fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['branch_id'], function ($query, $branchId) {
                $query->where(function ($branchQuery) use ($branchId) {
                    $branchQuery
                        ->where('old_branch_id', $branchId)
                        ->orWhere('new_branch_id', $branchId);
                });
            })
            ->when($filters['change_type'], function ($query, $type) {
                if ($type === 'branch') {
                    $query->where(function ($changeQuery) {
                        $changeQuery
                            ->whereColumn('old_branch_id', '!=', 'new_branch_id')
                            ->orWhere(function ($nullQuery) {
                                $nullQuery->whereNull('old_branch_id')->whereNotNull('new_branch_id');
                            })
                            ->orWhere(function ($nullQuery) {
                                $nullQuery->whereNotNull('old_branch_id')->whereNull('new_branch_id');
                            });
                    });
                }

                if ($type === 'role') {
                    $query->where(function ($changeQuery) {
                        $changeQuery
                            ->whereColumn('old_role', '!=', 'new_role')
                            ->orWhere(function ($nullQuery) {
                                $nullQuery->whereNull('old_role')->whereNotNull('new_role');
                            })
                            ->orWhere(function ($nullQuery) {
                                $nullQuery->whereNotNull('old_role')->whereNull('new_role');
                            });
                    });
                }

                if ($type === 'department') {
                    $query->where(function ($changeQuery) {
                        $changeQuery
                            ->whereColumn('old_department_id', '!=', 'new_department_id')
                            ->orWhere(function ($nullQuery) {
                                $nullQuery->whereNull('old_department_id')->whereNotNull('new_department_id');
                            })
                            ->orWhere(function ($nullQuery) {
                                $nullQuery->whereNotNull('old_department_id')->whereNull('new_department_id');
                            });
                    });
                }

                if ($type === 'status') {
                    $query->where(function ($changeQuery) {
                        $changeQuery
                            ->whereColumn('old_status', '!=', 'new_status')
                            ->orWhere(function ($nullQuery) {
                                $nullQuery->whereNull('old_status')->whereNotNull('new_status');
                            })
                            ->orWhere(function ($nullQuery) {
                                $nullQuery->whereNotNull('old_status')->whereNull('new_status');
                            });
                    });
                }
            })
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('effective_date', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('effective_date', '<=', $date));
    }

    private function filters(Request $request): array
    {
        return array_merge([
            'user_id' => null,
            'branch_id' => null,
            'change_type' => null,
            'date_from' => null,
            'date_to' => null,
        ], $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'change_type' => ['nullable', 'in:branch,role,department,status'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]));
    }
}
