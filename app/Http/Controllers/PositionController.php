<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(): View
    {
        $positions = Position::withCount('users')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('positions.index', compact('positions'));
    }

    public function create(): View
    {
        $systemRoles = Position::SYSTEM_ROLES;
        $nextSortOrder = (Position::max('sort_order') ?? 0) + 1;

        return view('positions.create', compact('systemRoles', 'nextSortOrder'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:positions,code'],
            'system_role' => ['required', Rule::in(array_keys(Position::SYSTEM_ROLES))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($data['code'])) {
            $data['code'] = Str::slug($data['name'], '_');
            // Ensure unique code
            $baseCode = $data['code'];
            $counter = 1;
            while (Position::where('code', $data['code'])->exists()) {
                $data['code'] = $baseCode . '_' . $counter++;
            }
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        Position::create($data);

        return redirect()
            ->route('positions.index')
            ->with('success', 'Đã thêm chức vụ mới thành công.');
    }

    public function edit(Position $position): View
    {
        $systemRoles = Position::SYSTEM_ROLES;

        return view('positions.edit', compact('position', 'systemRoles'));
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('positions', 'code')->ignore($position->id)],
            'system_role' => ['required', Rule::in(array_keys(Position::SYSTEM_ROLES))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldSystemRole = $position->system_role;
        $newSystemRole = $data['system_role'];

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        $position->update($data);

        // Nếu nhóm quyền thay đổi, tự động cập nhật lại cột role cho tất cả nhân sự đang giữ position này
        if ($oldSystemRole !== $newSystemRole) {
            User::where('position_id', $position->id)->update([
                'role' => $newSystemRole,
            ]);
        }

        return redirect()
            ->route('positions.index')
            ->with('success', 'Đã cập nhật thông tin chức vụ thành công.');
    }

    public function destroy(Position $position): RedirectResponse
    {
        $userCount = $position->users()->count();

        if ($userCount > 0) {
            return back()->with('error', "Không thể xóa chức vụ '{$position->name}' vì đang có {$userCount} nhân sự đảm nhiệm. Hãy chuyển các nhân sự sang chức vụ khác trước khi xóa.");
        }

        $position->delete();

        return redirect()
            ->route('positions.index')
            ->with('success', "Đã xóa chức vụ '{$position->name}' thành công.");
    }
}
