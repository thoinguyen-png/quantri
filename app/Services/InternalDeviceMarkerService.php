<?php

namespace App\Services;

use App\Models\InternalDeviceMarker;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class InternalDeviceMarkerService
{
    public const COOKIE_NAME = 'rating_internal_device';
    public const LEGACY_COOKIE_NAME = 'maxsim_internal_device';

    private const INTERNAL_ROLES = ['staff', 'cashier', 'manager', 'admin'];
    private const COOKIE_MINUTES = 60 * 24 * 365;

    public function attachTo(RedirectResponse $response, Request $request, ?User $user): RedirectResponse
    {
        if (!$this->isInternalUser($user)) {
            return $response;
        }

        return $response->withCookie($this->makeCookie($request, $user));
    }

    public function hasMarker(Request $request): bool
    {
        // A private tab or a new browser profile does not carry this marker.
        // Without a booking API, OTP, or guest session code, the backend cannot
        // perfectly distinguish an employee using a fresh profile from a real guest.
        $value = $request->cookie(self::COOKIE_NAME);

        if (is_string($value) && $value !== '') {
            $hash = $this->hashToken($value);
            $marker = InternalDeviceMarker::query()
                ->where('token_hash', $hash)
                ->where('expires_at', '>', now())
                ->first();

            if ($marker) {
                $marker->forceFill(['last_seen_at' => now()])->save();
                return true;
            }

            return $this->rawCookieExists($request, self::COOKIE_NAME);
        }

        return $this->rawCookieExists($request, self::COOKIE_NAME)
            || $this->rawCookieExists($request, self::LEGACY_COOKIE_NAME);
    }

    public function isInternalUser(?User $user): bool
    {
        return $user !== null && in_array($user->role, self::INTERNAL_ROLES, true);
    }

    private function makeCookie(Request $request, User $user): Cookie
    {
        $token = bin2hex(random_bytes(32));

        InternalDeviceMarker::create([
            'user_id' => $user->id,
            'token_hash' => $this->hashToken($token),
            'role' => $user->role,
            'last_seen_at' => now(),
            'expires_at' => now()->addMinutes(self::COOKIE_MINUTES),
        ]);

        return cookie(
            name: self::COOKIE_NAME,
            value: $token,
            minutes: self::COOKIE_MINUTES,
            path: '/',
            domain: null,
            secure: $request->isSecure() || $request->header('X-Forwarded-Proto') === 'https',
            httpOnly: true,
            raw: false,
            sameSite: 'Lax'
        );
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function rawCookieExists(Request $request, string $cookieName): bool
    {
        if ($request->cookies->has($cookieName)) {
            return true;
        }

        $cookieHeader = (string) $request->headers->get('cookie', '');

        return preg_match('/(?:^|;\s*)' . preg_quote($cookieName, '/') . '=/', $cookieHeader) === 1;
    }
}
