<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchShiftPermissionController extends Controller
{
    public function index(): View
    {
        return view('branch_shift_permissions.index', [
            'branches' => Branch::active()->with(['shifts' => fn ($query) => $query->orderBy('start_at')])->orderBy('name')->get(),
            'shifts' => Shift::orderBy('start_at')->get(),
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'shift_ids' => ['nullable', 'array'],
            'shift_ids.*' => ['integer', 'distinct', 'exists:shifts,id'],
        ]);

        $branch->shifts()->sync($data['shift_ids'] ?? []);

        return redirect()
            ->route('branch-shift-permissions.index')
            ->with('success', "Đã cập nhật các ca được phép cho cơ sở {$branch->name}.");
    }
}
