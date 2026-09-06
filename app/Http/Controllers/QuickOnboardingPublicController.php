<?php

namespace App\Http\Controllers;

use App\Models\QuickOnboardingEntry;
use App\Services\CitizenIdOcrService;
use App\Services\InternalDeviceMarkerService;
use App\Services\QuickOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuickOnboardingPublicController extends Controller
{
    private const PREVIEW_SESSION_PREFIX = 'quick_onboarding_preview_';

    public function show(string $token): View
    {
        $entry = $this->entryForToken($token);
        $draftData = session($this->previewSessionKey($entry), []);

        if (!is_array($draftData)) {
            $draftData = [];
        }

        if ($this->isCompleted($entry)) {
            return view('quick_onboarding.public.completed', [
                'entry' => $entry,
                'message' => 'Link dang ky nay da hoan tat.',
            ]);
        }

        return view('quick_onboarding.public.form', [
            'entry' => $entry,
            'ocrEnabled' => (bool) config('services.ocr.enabled', true),
            'ocrData' => session($this->ocrSessionKey($entry), []),
            'draftData' => $draftData,
            'draftAvatarUrl' => $this->resolveStoredImageUrl($draftData['avatar_path'] ?? $draftData['face_image_path'] ?? null),
            'draftCccdImageUrl' => $this->resolveStoredImageUrl($draftData['cccd_image_path'] ?? $draftData['citizen_id_image_path'] ?? null),
        ]);
    }

    public function ocr(string $token, Request $request, CitizenIdOcrService $ocr): JsonResponse
    {
        $entry = $this->entryForToken($token);

        abort_if($this->isCompleted($entry), 403);

        if (! (bool) config('services.ocr.enabled', true)) {
            return response()->json([
                'ok' => false,
                'message' => 'OCR đang tạm tắt. Vui lòng nhập thông tin thủ công.',
                'data' => $ocr->emptyResult(),
                'stub' => true,
            ], 403)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $request->validate([
            'cccd_image' => ['required', 'image', 'max:5120'],
        ]);

        $result = $ocr->extract($request->file('cccd_image'));
        $ok = $ocr->lastOk();

        session()->put($this->ocrSessionKey($entry), $result);

        return response()->json([
            'ok' => $ok,
            'message' => $ok
                ? 'Đã đọc thông tin. Vui lòng kiểm tra lại trước khi tiếp tục.'
                : 'Không đọc được ảnh. Vui lòng nhập thông tin thủ công hoặc thử ảnh rõ hơn.',
            'data' => $result,
            'stub' => $ocr->isStub(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function preview(string $token, Request $request): View|RedirectResponse
    {
        $entry = $this->entryForToken($token);

        if ($this->isCompleted($entry)) {
            return redirect()->route('quick-onboarding.public.show', $entry->token);
        }

        $existingDraft = session($this->previewSessionKey($entry), []);

        if (!is_array($existingDraft)) {
            $existingDraft = [];
        }

        $data = $this->validateRegistrationData($request, $entry, $existingDraft);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('faces', 'public');
            $data['face_image_path'] = $data['avatar_path'];
        } elseif (!empty($existingDraft['avatar_path']) || !empty($existingDraft['face_image_path'])) {
            $data['avatar_path'] = $existingDraft['avatar_path'] ?? $existingDraft['face_image_path'];
            $data['face_image_path'] = $existingDraft['face_image_path'] ?? $existingDraft['avatar_path'];
        }

        $citizenIdImage = $request->file('cccd_image') ?: $request->file('electronic_image');

        if ($citizenIdImage) {
            $data['cccd_image_path'] = $citizenIdImage->store('quick-onboarding/citizen-ids', 'public');
        } elseif (!empty($existingDraft['cccd_image_path']) || !empty($existingDraft['citizen_id_image_path'])) {
            $data['cccd_image_path'] = $existingDraft['cccd_image_path'] ?? $existingDraft['citizen_id_image_path'];
        }

        unset($data['avatar'], $data['electronic_image'], $data['cccd_image']);

        session()->put($this->previewSessionKey($entry), $data);

        return view('quick_onboarding.public.preview', [
            'entry' => $entry,
            'data' => $data,
        ]);
    }

    public function confirm(
        string $token,
        Request $request,
        QuickOnboardingService $onboarding,
        InternalDeviceMarkerService $internalDeviceMarker
    ): View|RedirectResponse {
        $entry = $this->entryForToken($token);

        if ($this->isCompleted($entry)) {
            return redirect()->route('quick-onboarding.public.show', $entry->token);
        }

        $data = session($this->previewSessionKey($entry));

        if (!is_array($data) || !$this->previewDataIsValid($data)) {
            return redirect()
                ->route('quick-onboarding.public.show', $entry->token)
                ->withErrors(['preview' => 'Vui long kiem tra thong tin truoc khi xac nhan.']);
        }

        $user = $onboarding->createUserFromOnboarding($entry, $data);
        session()->forget($this->previewSessionKey($entry));
        session()->forget($this->ocrSessionKey($entry));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $redirect = redirect()->route('dashboard');

        return $internalDeviceMarker->attachTo($redirect, $request, $user);
    }

    private function entryForToken(string $token): QuickOnboardingEntry
    {
        return QuickOnboardingEntry::query()
            ->with(['branch', 'completedUser'])
            ->where('token', $token)
            ->firstOrFail();
    }

    private function validateRegistrationData(Request $request, QuickOnboardingEntry $entry, array $existingDraft = []): array
    {
        $hasStoredPassword = trim((string) ($existingDraft['password'] ?? '')) !== '';

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'citizen_id' => ['nullable', 'string', 'max:32'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:32'],
            'place_of_origin' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:500'],
            'issue_date' => ['nullable', 'date'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => [$hasStoredPassword ? 'nullable' : 'required', 'string', 'min:6'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'electronic_image' => ['nullable', 'image', 'max:5120'],
            'cccd_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $data['name'] = trim((string) ($data['name'] ?? '')) ?: (string) ($entry->expected_name ?? '');
        $data['password'] = trim((string) ($data['password'] ?? '')) !== ''
            ? $data['password']
            : ($existingDraft['password'] ?? null);

        if (trim($data['name']) === '') {
            throw ValidationException::withMessages([
                'name' => 'Vui long nhap ho ten.',
            ]);
        }

        return $data;
    }

    private function previewDataIsValid(array $data): bool
    {
        foreach (['name', 'phone', 'password'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                return false;
            }
        }

        return true;
    }

    private function resolveStoredImageUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:image')) {
            return $path;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            return null;
        }

        $relativePath = ltrim($path, '/');
        $localCandidates = [];

        if (str_starts_with($relativePath, 'storage/')) {
            $localCandidates[] = public_path($relativePath);
            $relativePath = substr($relativePath, strlen('storage/'));
        }

        $localCandidates[] = storage_path('app/public/' . $relativePath);
        $localCandidates[] = public_path($relativePath);

        foreach ($localCandidates as $localPath) {
            if (!is_file($localPath)) {
                continue;
            }

            $mime = mime_content_type($localPath) ?: 'image/jpeg';
            $contents = file_get_contents($localPath);

            if ($contents !== false) {
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            }
        }

        if (str_starts_with($path, '/storage/')) {
            return asset(ltrim($path, '/'));
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return null;
    }

    private function isCompleted(QuickOnboardingEntry $entry): bool
    {
        return $entry->status === QuickOnboardingService::STATUS_COMPLETED;
    }

    private function previewSessionKey(QuickOnboardingEntry $entry): string
    {
        return self::PREVIEW_SESSION_PREFIX . $entry->id;
    }

    private function ocrSessionKey(QuickOnboardingEntry $entry): string
    {
        return 'quick_onboarding_ocr_' . $entry->id;
    }
}
