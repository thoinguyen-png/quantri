<?php

namespace App\Services;

use Illuminate\Support\Str;

class GuestBrowserTokenService
{
    public function makeToken(): string
    {
        return Str::random(64);
    }

    public function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    public function hashNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
