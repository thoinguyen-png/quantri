<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class EmployeeCodeService
{
    public function resolveForBranch(?int $branchId, ?string $requestedCode = null, ?int $ignoreUserId = null): ?string
    {
        $code = $this->normalize($requestedCode);

        if ($branchId === null) {
            return $code;
        }

        if ($code === null) {
            $code = $this->nextForBranch($branchId);
        }

        $this->ensureAvailable($branchId, $code, $ignoreUserId);

        return $code;
    }

    public function normalize(?string $code): ?string
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        if (!preg_match('/^\d{1,4}$/', $code)) {
            throw ValidationException::withMessages([
                'employee_code' => 'Ma nhan su chi gom so va toi da 4 chu so.',
            ]);
        }

        return str_pad($code, 4, '0', STR_PAD_LEFT);
    }

    public function nextForBranch(int $branchId): string
    {
        $codes = User::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('employee_code')
            ->lockForUpdate()
            ->pluck('employee_code');

        $max = 0;

        foreach ($codes as $code) {
            if (is_string($code) && preg_match('/^\d{4}$/', $code)) {
                $max = max($max, (int) $code);
            }
        }

        $next = $max + 1;

        if ($next > 9999) {
            throw ValidationException::withMessages([
                'employee_code' => 'Chi nhanh nay da het kho ma nhan su 0001-9999.',
            ]);
        }

        return str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function ensureAvailable(int $branchId, string $code, ?int $ignoreUserId = null): void
    {
        $exists = User::query()
            ->where('branch_id', $branchId)
            ->where('employee_code', $code)
            ->when($ignoreUserId !== null, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'employee_code' => 'Ma nhan su nay da ton tai trong chi nhanh.',
            ]);
        }
    }
}
