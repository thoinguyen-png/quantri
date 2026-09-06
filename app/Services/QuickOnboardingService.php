<?php

namespace App\Services;

use App\Models\QuickOnboardingBatch;
use App\Models\QuickOnboardingEntry;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class QuickOnboardingService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_COMPLETED = 'completed';

    public function createBatch(User $creator, int $quantity, int $branchId): QuickOnboardingBatch
    {
        return DB::transaction(function () use ($creator, $quantity, $branchId) {
            $batch = QuickOnboardingBatch::create([
                'created_by' => $creator->id,
                'branch_id' => $branchId,
                'entries_count' => 0,
            ]);

            $this->addEntries($batch, $creator, $quantity, $branchId);

            return $batch->fresh(['branch', 'entries.branch']);
        });
    }

    public function addToBatch(QuickOnboardingBatch $batch, User $creator, int $quantity, int $branchId): QuickOnboardingBatch
    {
        return DB::transaction(function () use ($batch, $creator, $quantity, $branchId) {
            $locked = QuickOnboardingBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->addEntries($locked, $creator, $quantity, $branchId);

            return $locked->fresh(['branch', 'entries.branch']);
        });
    }

    public function canAccessBatch(User $user, QuickOnboardingBatch $batch): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'manager'
            && (int) $batch->created_by === (int) $user->id
            && (int) $batch->branch_id === (int) $user->branch_id;
    }

    public function canAccessEntry(User $user, QuickOnboardingEntry $entry): bool
    {
        $entry->loadMissing('batch');

        return $entry->batch !== null
            && $this->canAccessBatch($user, $entry->batch);
    }

    public function bulkUpdateEntries(QuickOnboardingBatch $batch, User $actor, array $entryIds, int $branchId, string $role): int
    {
        return DB::transaction(function () use ($batch, $actor, $entryIds, $branchId, $role) {
            $lockedBatch = QuickOnboardingBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->canAccessBatch($actor, $lockedBatch)) {
                abort(403);
            }

            return QuickOnboardingEntry::query()
                ->where('quick_onboarding_batch_id', $lockedBatch->id)
                ->whereIn('id', $entryIds)
                ->update([
                    'branch_id' => $branchId,
                    'intended_role' => $role,
                    'updated_at' => now(),
                ]);
        });
    }

    public function markEntryAsSent(QuickOnboardingEntry $entry, User $actor): QuickOnboardingEntry
    {
        return DB::transaction(function () use ($entry, $actor) {
            $locked = QuickOnboardingEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->canAccessEntry($actor, $locked)) {
                abort(403);
            }

            if ($locked->status !== self::STATUS_COMPLETED) {
                $locked->forceFill([
                    'status' => self::STATUS_SENT,
                    'sent_at' => $locked->sent_at ?? now(),
                ])->save();
            }

            return $locked->fresh(['completedUser']);
        });
    }

    public function updateExpectedName(QuickOnboardingEntry $entry, User $actor, ?string $expectedName): QuickOnboardingEntry
    {
        return DB::transaction(function () use ($entry, $actor, $expectedName) {
            $locked = QuickOnboardingEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->canAccessEntry($actor, $locked)) {
                abort(403);
            }

            if ($locked->status === self::STATUS_COMPLETED) {
                return $locked->fresh(['completedUser']);
            }

            $locked->forceFill([
                'expected_name' => $expectedName ?: null,
            ])->save();

            return $locked->fresh(['completedUser']);
        });
    }

    public function updateEntryRole(QuickOnboardingEntry $entry, User $actor, string $role): QuickOnboardingEntry
    {
        return DB::transaction(function () use ($entry, $actor, $role) {
            $locked = QuickOnboardingEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->canAccessEntry($actor, $locked)) {
                abort(403);
            }

            if ($locked->status === self::STATUS_COMPLETED) {
                return $locked->fresh(['branch', 'completedUser']);
            }

            $locked->forceFill([
                'intended_role' => $role,
            ])->save();

            return $locked->fresh(['branch', 'completedUser']);
        });
    }

    public function updateEntryAvatar(QuickOnboardingEntry $entry, User $actor, UploadedFile $avatar): QuickOnboardingEntry
    {
        return DB::transaction(function () use ($entry, $actor, $avatar) {
            $locked = QuickOnboardingEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->canAccessEntry($actor, $locked)) {
                abort(403);
            }

            if ($locked->status === self::STATUS_COMPLETED) {
                return $locked->fresh(['branch', 'completedUser']);
            }

            $path = $avatar->store('quick-onboarding/avatars', 'public');

            $locked->forceFill([
                'avatar_path' => $path,
            ])->save();

            return $locked->fresh(['branch', 'completedUser']);
        });
    }

    public function deleteEntry(QuickOnboardingEntry $entry, User $actor): void
    {
        DB::transaction(function () use ($entry, $actor) {
            $locked = QuickOnboardingEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->canAccessEntry($actor, $locked)) {
                abort(403);
            }

            if ($locked->status === self::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'entry' => 'Loi moi da hoan tat, khong the xoa.',
                ]);
            }

            $batchId = $locked->quick_onboarding_batch_id;
            $locked->delete();

            QuickOnboardingBatch::query()
                ->whereKey($batchId)
                ->where('entries_count', '>', 0)
                ->decrement('entries_count');
        });
    }


    public function markEntryAsCompleted(QuickOnboardingEntry $entry, User $completedUser): QuickOnboardingEntry
    {
        $entry->forceFill([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_user_id' => $completedUser->id,
            'used_at' => $entry->used_at ?? now(),
        ])->save();

        return $entry->fresh(['completedUser']);
    }

    public function completeFromOnboarding(QuickOnboardingEntry $entry, array $payload): QuickOnboardingEntry
    {
        return DB::transaction(function () use ($entry, $payload) {
            $locked = QuickOnboardingEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === self::STATUS_COMPLETED) {
                return $locked->fresh(['completedUser']);
            }

            unset($payload['password'], $payload['password_confirmation']);

            $locked->forceFill([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
                'used_at' => $locked->used_at ?? now(),
                'submitted_payload' => $payload,
            ])->save();

            return $locked->fresh(['completedUser']);
        });
    }

    public function createUserFromOnboarding(QuickOnboardingEntry $entry, array $payload): User
    {
        return DB::transaction(function () use ($entry, $payload) {
            $locked = QuickOnboardingEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === self::STATUS_COMPLETED || $locked->completed_user_id) {
                throw ValidationException::withMessages([
                    'token' => 'Link dang ky nay da hoan tat.',
                ]);
            }

            if (!$locked->branch_id || !$locked->intended_role) {
                throw ValidationException::withMessages([
                    'token' => 'Loi moi chua duoc gan chi nhanh hoac chuc vu.',
                ]);
            }

            if (!in_array($locked->intended_role, ['manager', 'staff', 'cashier'], true)) {
                throw ValidationException::withMessages([
                    'token' => 'Chuc vu trong loi moi khong hop le.',
                ]);
            }

            $startWorkDate = today()->toDateString();
            $creatorId = $locked->created_by;
            $safePayload = $payload;
            unset($safePayload['password'], $safePayload['password_confirmation']);

            $user = User::create([
                'name' => $payload['name'],
                'email' => ($payload['email'] ?? null) ?: null,
                'phone' => $payload['phone'],
                'zalo_phone' => null,
                'citizen_id' => $payload['citizen_id'] ?? null,
                'employee_code' => app(EmployeeCodeService::class)->resolveForBranch((int) $locked->branch_id),
                'start_work_date' => $startWorkDate,
                'password' => Hash::make($payload['password']),
                'role' => $locked->intended_role,
                'status' => 'thu_viec',
                'employment_status' => 'probation',
                'hired_at' => $startWorkDate,
                'hired_by' => $creatorId,
                'official_at' => null,
                'official_by' => null,
                'resigned_at' => null,
                'resigned_by' => null,
                'status_changed_by' => $creatorId,
                'status_changed_at' => now(),
                'branch_id' => $locked->branch_id,
                'face_image_path' => $payload['avatar_path'] ?? $payload['face_image_path'] ?? null,
                'face_verification_mode' => 'priority',
                'is_active' => true,
            ]);

            $locked->forceFill([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_user_id' => $user->id,
                'used_at' => $locked->used_at ?? now(),
                'submitted_payload' => $safePayload,
            ])->save();

            return $user;
        });
    }

    public function syncPayload(QuickOnboardingEntry $entry): array
    {
        $entry->loadMissing(['branch', 'completedUser']);

        return [
            'id' => $entry->id,
            'demo_code' => $entry->demo_code,
            'completed_user_id' => $entry->completed_user_id,
            'employee_name' => $entry->completedUser?->name ?? ($entry->submitted_payload['name'] ?? null),
            'expected_name' => $entry->expected_name,
            'branch_name' => $entry->branch?->name,
            'intended_role' => $entry->intended_role,
            'role_label' => $this->roleLabel($entry->intended_role),
            'status' => $entry->status ?: self::STATUS_DRAFT,
            'status_label' => $this->statusLabel($entry),
            'color_class' => $this->rowColorClass($entry),
            'avatar_url' => $this->avatarUrl($entry),
            'invite_url' => $entry->invite_url,
        ];
    }

    private function rowColorClass(QuickOnboardingEntry $entry): string
    {
        return match ($entry->status) {
            self::STATUS_COMPLETED => 'quick-entry--completed',
            self::STATUS_SENT => 'quick-entry--sent',
            default => 'quick-entry--draft',
        };
    }

    private function addEntries(QuickOnboardingBatch $batch, User $creator, int $quantity, int $branchId): void
    {
        $rows = [];
        $now = now();
        $nextNumber = $this->nextDemoNumber($batch);

        if (($nextNumber + $quantity - 1) > 999) {
            throw ValidationException::withMessages([
                'quantity' => 'Ma demo chi ho tro toi da 999 loi moi trong mot batch.',
            ]);
        }

        for ($i = 0; $i < $quantity; $i++) {
            $rows[] = [
                'quick_onboarding_batch_id' => $batch->id,
                'demo_code' => str_pad((string) ($nextNumber + $i), 3, '0', STR_PAD_LEFT),
                'created_by' => $creator->id,
                'branch_id' => $branchId,
                'intended_role' => 'staff',
                'token' => $this->uniqueToken(),
                'status' => self::STATUS_DRAFT,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        QuickOnboardingEntry::insert($rows);

        $batch->increment('entries_count', $quantity);
    }

    private function nextDemoNumber(QuickOnboardingBatch $batch): int
    {
        $maxCode = QuickOnboardingEntry::query()
            ->where('quick_onboarding_batch_id', $batch->id)
            ->whereNotNull('demo_code')
            ->max('demo_code');

        if (is_numeric($maxCode)) {
            return ((int) $maxCode) + 1;
        }

        return ((int) $batch->entries()->count()) + 1;
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(72);
        } while (QuickOnboardingEntry::where('token', $token)->exists());

        return $token;
    }

    private function statusLabel(QuickOnboardingEntry $entry): string
    {
        return match ($entry->status) {
            self::STATUS_COMPLETED => 'Hoan tat',
            self::STATUS_SENT => 'Da gui',
            default => 'Chua gui',
        };
    }

    private function roleLabel(?string $role): string
    {
        return match ($role) {
            'manager' => 'Quan ly',
            'cashier' => 'Thu ngan',
            default => 'Nhan vien',
        };
    }

    private function avatarUrl(QuickOnboardingEntry $entry): ?string
    {
        $path = $entry->avatar_path
            ?: $entry->completedUser?->face_image_path
            ?: ($entry->submitted_payload['avatar_path'] ?? null)
            ?: ($entry->submitted_payload['face_image_path'] ?? null);

        if (!$path) {
            return null;
        }

        $path = trim((string) $path);

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            return null;
        }

        if (Str::startsWith($path, '/')) {
            $publicPath = ltrim($path, '/');

            return file_exists(public_path($publicPath))
                ? asset($publicPath)
                : null;
        }

        if (Str::startsWith($path, 'storage/')) {
            if (file_exists(public_path($path))) {
                return asset($path);
            }

            $diskPath = Str::after($path, 'storage/');

            return $this->publicDiskImageUrl($diskPath);
        }

        $diskUrl = $this->publicDiskImageUrl($path);

        if ($diskUrl) {
            return $diskUrl;
        }

        return file_exists(public_path($path))
            ? asset($path)
            : null;
    }

    private function publicDiskImageUrl(string $path): ?string
    {
        $path = ltrim($path, '/');

        if (!Storage::disk('public')->exists($path)) {
            return null;
        }

        $publicPath = 'storage/' . $path;

        if (file_exists(public_path($publicPath))) {
            return asset($publicPath);
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
        $contents = Storage::disk('public')->get($path);

        if ($contents === false || $contents === null || $contents === '') {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
