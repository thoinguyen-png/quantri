<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BranchController extends Controller
{
    private const LOGO_DISK = 'public';
    private const LOGO_DIR = 'staff-card-logos';

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');

        $query = Branch::query()
            ->withCount([
                'users as active_users_count' => fn ($q) => $q->where('is_active', true),
                'users as total_users_count',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name');

        $branches = $query->get();

        $stats = [
            'total' => Branch::count(),
            'active' => Branch::where('is_active', true)->count(),
            'inactive' => Branch::where('is_active', false)->count(),
        ];

        return view('branches.index', [
            'branches' => $branches,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): View
    {
        return view('branches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:500'],
                'hotline' => ['nullable', 'string', 'max:50'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'gps_radius' => ['nullable', 'integer', 'min:10', 'max:5000'],
                'is_active' => ['nullable', 'boolean'],
                'staff_card_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
            ],
            [
                'name.required' => 'Vui lòng nhập tên cơ sở / chi nhánh.',
                'staff_card_logo.mimes' => 'Logo phải có định dạng PNG, JPG, JPEG hoặc WebP.',
                'staff_card_logo.max' => 'Logo không được vượt quá 10 MB.',
                'gps_radius.min' => 'Bán kính GPS tối thiểu là 10 mét.',
                'gps_radius.max' => 'Bán kính GPS tối đa là 5.000 mét.',
            ]
        );

        $branch = Branch::create([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'hotline' => $validated['hotline'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'gps_radius' => $validated['gps_radius'] ?? 100,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->hasFile('staff_card_logo')) {
            $newPath = $request->file('staff_card_logo')->store(self::LOGO_DIR . '/' . $branch->id, self::LOGO_DISK);
            $branch->forceFill(['staff_card_logo_path' => $newPath])->save();
        }

        return redirect()
            ->route('branches.index')
            ->with('success', 'Đã thêm cơ sở mới: ' . $branch->name);
    }

    public function edit(Branch $branch): View
    {
        $branch->loadCount([
            'users as active_users_count' => fn ($q) => $q->where('is_active', true),
            'users as total_users_count',
        ]);

        return view('branches.edit', [
            'branch' => $branch,
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:500'],
                'hotline' => ['nullable', 'string', 'max:50'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'gps_radius' => ['nullable', 'integer', 'min:10', 'max:5000'],
                'is_active' => ['nullable', 'boolean'],
                'remove_logo' => ['nullable', 'boolean'],
                'staff_card_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
            ],
            [
                'name.required' => 'Vui lòng nhập tên cơ sở / chi nhánh.',
                'staff_card_logo.mimes' => 'Logo phải có định dạng PNG, JPG, JPEG hoặc WebP.',
                'staff_card_logo.max' => 'Logo không được vượt quá 10 MB.',
                'gps_radius.min' => 'Bán kính GPS tối thiểu là 10 mét.',
                'gps_radius.max' => 'Bán kính GPS tối đa là 5.000 mét.',
            ]
        );

        $branch->name = $validated['name'];
        $branch->address = $validated['address'] ?? null;
        $branch->hotline = $validated['hotline'] ?? null;
        $branch->latitude = $validated['latitude'] ?? null;
        $branch->longitude = $validated['longitude'] ?? null;
        $branch->gps_radius = $validated['gps_radius'] ?? 100;
        $branch->is_active = $request->boolean('is_active');

        // Xóa logo nếu có yêu cầu
        if ($request->boolean('remove_logo')) {
            $this->deleteStoredLogo($branch);
            $branch->staff_card_logo_path = null;
        }

        // Tải logo mới thay thế
        if ($request->hasFile('staff_card_logo')) {
            $this->deleteStoredLogo($branch);
            $newPath = $request->file('staff_card_logo')->store(self::LOGO_DIR . '/' . $branch->id, self::LOGO_DISK);
            $branch->staff_card_logo_path = $newPath;
        }

        $branch->save();

        $message = 'Đã cập nhật thông tin cơ sở: ' . $branch->name;
        if (! $branch->is_active) {
            $deactivatedCount = $branch->deactivateStaff();
            if ($deactivatedCount > 0) {
                $message .= " (Đã tự động chuyển {$deactivatedCount} nhân sự trực thuộc sang trạng thái 'Đã nghỉ').";
            }
        }

        return redirect()
            ->route('branches.index')
            ->with('success', $message);
    }

    public function toggleStatus(Branch $branch): RedirectResponse
    {
        $branch->is_active = ! $branch->is_active;
        $branch->save();

        $statusText = $branch->is_active ? 'Đang hoạt động' : 'Ngưng hoạt động';
        $message = "Đã chuyển trạng thái cơ sở {$branch->name} sang: {$statusText}.";

        if (! $branch->is_active) {
            $deactivatedCount = $branch->deactivateStaff();
            if ($deactivatedCount > 0) {
                $message .= " (Đã tự động chuyển {$deactivatedCount} nhân sự trực thuộc sang trạng thái 'Đã nghỉ').";
            }
        }

        return back()->with('success', $message);
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $userCount = $branch->users()->count();

        if ($userCount > 0) {
            return back()->with('error', "Không thể xóa cơ sở '{$branch->name}' vì đang có {$userCount} nhân sự trực thuộc. Bạn có thể chuyển cơ sở sang trạng thái 'Ngưng hoạt động'.");
        }

        $this->deleteStoredLogo($branch);
        $name = $branch->name;
        $branch->delete();

        return redirect()
            ->route('branches.index')
            ->with('success', "Đã xóa cơ sở: {$name}");
    }

    private function deleteStoredLogo(Branch $branch): void
    {
        $path = ltrim(trim((string) $branch->staff_card_logo_path), '/');

        if ($path !== '' && Storage::disk(self::LOGO_DISK)->exists($path)) {
            Storage::disk(self::LOGO_DISK)->delete($path);
        }
    }
}
