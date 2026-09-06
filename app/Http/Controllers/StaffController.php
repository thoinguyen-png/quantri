<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $staffs = User::where('role', 'staff')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'da_nghi');
            })
            ->when($user->role === 'manager', function ($query) use ($user) {
                $query->where('branch_id', $user->branch_id);
            })
            ->with('branch')
            ->latest()
            ->get();

        return view('staff.index', compact('staffs'));
    }

    public function create()
    {
        $branches = Branch::active()->orderBy('name')->get();

        return view('staff.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable'],
            'password' => ['required', 'min:6'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        if ($user->role === 'manager') {
            $data['branch_id'] = $user->branch_id;
        }

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'staff',
            'branch_id' => $data['branch_id'],
        ]);

        return redirect()->route('users.index')->with('success', 'Đã tạo nhân viên.');
    }
}
