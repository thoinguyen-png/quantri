<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class BranchStaffCardLogoController extends Controller
{
    private const DISK = 'public';
    private const DIRECTORY = 'staff-card-logos';

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        Gate::authorize('updateStaffCardLogo', $branch);

        $validated = $request->validate(
            [
                'staff_card_logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
            ],
            [
                'staff_card_logo.required' => 'Vui lòng chọn logo cần tải lên.',
                'staff_card_logo.file' => 'Logo tải lên không phải là một tệp hợp lệ.',
                'staff_card_logo.uploaded' => 'Không thể tải logo lên. Vui lòng kiểm tra lại dung lượng tệp.',
                'staff_card_logo.mimes' => 'Logo phải có định dạng PNG, JPG, JPEG hoặc WebP.',
                'staff_card_logo.max' => 'Logo không được vượt quá 10 MB.',
            ],
        );

        $disk = Storage::disk(self::DISK);
        $oldPath = $this->safeStoredPath($branch, $branch->staff_card_logo_path);
        $newPath = $validated['staff_card_logo']->store(self::DIRECTORY . '/' . $branch->id, self::DISK);

        try {
            $branch->forceFill(['staff_card_logo_path' => $newPath])->saveOrFail();
        } catch (\Throwable $exception) {
            $disk->delete($newPath);
            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            $disk->delete($oldPath);
        }

        return back()->with('success', 'Da cap nhat logo the cho co so ' . $branch->name . '.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        Gate::authorize('updateStaffCardLogo', $branch);

        $oldPath = $this->safeStoredPath($branch, $branch->staff_card_logo_path);
        $branch->forceFill(['staff_card_logo_path' => null])->saveOrFail();

        if ($oldPath !== null) {
            Storage::disk(self::DISK)->delete($oldPath);
        }

        return back()->with('success', 'Da xoa logo rieng; the se dung logo mac dinh.');
    }

    private function safeStoredPath(Branch $branch, ?string $path): ?string
    {
        $path = ltrim(trim((string) $path), '/');
        $branchDirectory = self::DIRECTORY . '/' . $branch->id . '/';

        if (
            $path === ''
            || ! str_starts_with($path, $branchDirectory)
            || str_contains($path, '..')
            || str_contains($path, '\\')
        ) {
            return null;
        }

        return $path;
    }
}
