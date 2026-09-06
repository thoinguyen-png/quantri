<?php

namespace App\Services;

use App\Models\RatingQrToken;
use App\Models\User;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RatingQrService
{
    public const PUBLIC_CODE_CHARSET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const PUBLIC_CODE_LENGTH = 8;

    public function getOrCreateForUser(User $user, ?User $actor = null): RatingQrToken
    {
        if (!$this->canHaveRatingQr($user)) {
            throw new RuntimeException('Nhan su nay khong du dieu kien co QR danh gia.');
        }

        $this->ensurePublicCode($user);

        $activeToken = $this->activeTokenForUser($user);

        if ($activeToken && $this->rawToken($activeToken) !== null) {
            return $activeToken;
        }

        return DB::transaction(function () use ($user, $actor) {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->first();
            $this->ensurePublicCode($user);

            $activeToken = $this->activeTokenForUser($user);

            if ($activeToken && $this->rawToken($activeToken) !== null) {
                return $activeToken;
            }

            return $this->createToken($user, $actor);
        });
    }

    public function regenerateForUser(User $user, User $actor): RatingQrToken
    {
        if (!in_array($user->role, ['staff', 'cashier', 'manager'], true)) {
            throw new RuntimeException('Chi nhan su moi co QR danh gia.');
        }

        return DB::transaction(function () use ($user, $actor) {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->first();

            RatingQrToken::query()
                ->where('user_id', $user->id)
                ->where('enabled', true)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get()
                ->each(function (RatingQrToken $token) use ($actor) {
                    $token->update([
                        'enabled' => false,
                        'revoked_at' => now(),
                        'regenerated_at' => now(),
                        'updated_by' => $actor->id,
                    ]);
                });

            if (!$user->rating_qr_enabled) {
                $user->update(['rating_qr_enabled' => true]);
            }

            $this->ensurePublicCode($user);

            return $this->createToken($user, $actor);
        });
    }

    public function disableForUser(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor) {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->first();
            $user->update(['rating_qr_enabled' => false]);

            RatingQrToken::query()
                ->where('user_id', $user->id)
                ->where('enabled', true)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get()
                ->each(function (RatingQrToken $token) use ($actor) {
                    $token->update([
                        'enabled' => false,
                        'revoked_at' => now(),
                        'updated_by' => $actor->id,
                    ]);
                });
        });
    }

    public function enableForUser(User $user, User $actor): RatingQrToken
    {
        if (!in_array($user->role, ['staff', 'cashier', 'manager'], true)) {
            throw new RuntimeException('Chi nhan su moi co QR danh gia.');
        }

        if (!$user->rating_qr_enabled) {
            $user->update(['rating_qr_enabled' => true]);
        }

        return $this->getOrCreateForUser($user->fresh(), $actor);
    }

    public function activeTokenForUser(User $user): ?RatingQrToken
    {
        return RatingQrToken::query()
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();
    }

    public function rawToken(RatingQrToken $token): ?string
    {
        if (!$token->token_ciphertext) {
            return null;
        }

        try {
            return Crypt::decryptString($token->token_ciphertext);
        } catch (\Throwable) {
            return null;
        }
    }

    public function publicUrl(RatingQrToken $token): string
    {
        $token->loadMissing('user');

        return $this->publicUrlForUser($token->user);
    }

    public function publicUrlForUser(User $user): string
    {
        $code = $this->ensurePublicCode($user);
        $baseUrl = rtrim((string) config('app.url'), '/');

        return $baseUrl . '/' . $code;
    }

    public function svgForToken(RatingQrToken $token, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($this->publicUrl($token), ecLevel: ErrorCorrectionLevel::M());
    }

    public function canHaveRatingQr(User $user): bool
    {
        return in_array($user->role, ['staff', 'cashier', 'manager'], true)
            && (bool) $user->rating_qr_enabled;
    }

    public function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    public function ensurePublicCode(User $user): string
    {
        if (is_string($user->public_rating_code) && $this->isValidPublicCode($user->public_rating_code)) {
            return $user->public_rating_code;
        }

        return DB::transaction(function () use ($user) {
            $locked = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (is_string($locked->public_rating_code) && $this->isValidPublicCode($locked->public_rating_code)) {
                $user->forceFill(['public_rating_code' => $locked->public_rating_code]);

                return $locked->public_rating_code;
            }

            do {
                $code = $this->makePublicCode();
            } while (User::where('public_rating_code', $code)->whereKeyNot($locked->id)->exists());

            $locked->update(['public_rating_code' => $code]);
            $user->forceFill(['public_rating_code' => $code]);

            return $code;
        });
    }

    public function isValidPublicCode(?string $code): bool
    {
        return is_string($code)
            && preg_match('/^[' . self::PUBLIC_CODE_CHARSET . ']{' . self::PUBLIC_CODE_LENGTH . '}$/', $code) === 1;
    }

    private function createToken(User $user, ?User $actor): RatingQrToken
    {
        do {
            $rawToken = Str::random(80);
            $hash = $this->hashToken($rawToken);
        } while (RatingQrToken::where('token_hash', $hash)->exists());

        return RatingQrToken::create([
            'user_id' => $user->id,
            'token_hash' => $hash,
            'token_ciphertext' => Crypt::encryptString($rawToken),
            'enabled' => true,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    private function makePublicCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::PUBLIC_CODE_LENGTH; $i++) {
            $code .= self::PUBLIC_CODE_CHARSET[random_int(0, strlen(self::PUBLIC_CODE_CHARSET) - 1)];
        }

        return $code;
    }
}
