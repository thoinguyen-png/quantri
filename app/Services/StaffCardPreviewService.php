<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class StaffCardPreviewService
{
    private ?string $whiteMaxsimLogoDataUri = null;

    public function __construct(
        private readonly RatingQrService $ratingQr,
    ) {}

    public function dataFor(User $user, bool $forPdf = false): array
    {
        $user->loadMissing(['branch', 'position']);

        $digits = preg_replace('/\D+/', '', (string) $user->citizen_id) ?? '';

        $activeQr = $user->relationLoaded('activeRatingQrToken')
            ? $user->activeRatingQrToken
            : $this->ratingQr->activeTokenForUser($user);

        if ($activeQr !== null && ! $activeQr->relationLoaded('user')) {
            $activeQr->setRelation('user', $user);
        }

        $positionEn = $user->position?->name_en ?: $this->positionEnglishLabel($user->role);
        $positionVi = $user->position?->name ?: $this->positionLabel($user->role);

        return [
            'name' => trim((string) $user->name) ?: 'Chưa có họ tên',

            'position' => $positionEn,
            'position_vi' => $positionVi,
            'position_en' => $positionEn,

            'avatar_url' => $forPdf
                ? $this->avatarDataUri($user)
                : $user->avatar_url,

            // Logo chính của cơ sở: Santori hoặc logo riêng đã upload.
            'branch_logo_url' => $forPdf
                ? $this->branchLogoDataUri($user)
                : (
                    $user->branch?->staff_card_logo_url
                    ?? asset('icons/logoSantori.png')
                ),

            // Logo MAXSIM chỉ dùng riêng cho mẫu A5.
            'maxsim_logo_url' => $forPdf
                ? $this->whiteMaxsimLogoDataUri()
                : asset('icons/logoMaxSim.png'),

            'citizen_last4' => strlen($digits) >= 4
                ? substr($digits, -4)
                : '----',

            'phone_masked' => $this->formatMaskedPhone($user->phone),
            'hotline' => $this->branchHotline($user->branch),
            'work_hours' => '11:00 - 05:00',
            'slogan' => 'HÀI LÒNG - QUAN TÂM - TRÂN TRỌNG',

            'qr_svg_url' => $activeQr
                ? (
                    $forPdf
                    ? 'data:image/svg+xml;base64,' .
                    base64_encode(
                        $this->ratingQr->svgForToken($activeQr, 600)
                    )
                    : route('rating-qrs.svg', $user, absolute: false)
                )
                : null,

            'has_rating_qr' => $activeQr !== null,
        ];
    }

    public function hasUsableRatingQr(User $user): bool
    {
        /*
     * Nhân sự đã tắt QR thì không được xuất thẻ.
     */
        if (! (bool) $user->rating_qr_enabled) {
            return false;
        }

        $activeQr = $user->relationLoaded('activeRatingQrToken')
            ? $user->activeRatingQrToken
            : $this->ratingQr->activeTokenForUser($user);

        /*
     * Không còn token đang hoạt động:
     * có thể chưa cấp, đã thu hồi hoặc đã hết hiệu lực.
     */
        if ($activeQr === null) {
            return false;
        }

        if (! $activeQr->relationLoaded('user')) {
            $activeQr->setRelation('user', $user);
        }

        /*
     * Thử tạo QR thật.
     * Nếu dữ liệu token lỗi hoặc thư viện QR lỗi thì xem như QR hỏng.
     */
        try {
            $svg = $this->ratingQr->svgForToken(
                $activeQr,
                240
            );

            return trim((string) $svg) !== '';
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function branchLogoDataUri(User $user): string
    {
        $path = trim((string) $user->branch?->staff_card_logo_path);

        if (
            $path !== ''
            && Storage::disk('public')->exists($path)
        ) {
            $contents = Storage::disk('public')->get($path);

            $mime = Storage::disk('public')->mimeType($path)
                ?: 'image/png';

            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        }

        return $this->publicImageDataUri('icons/logoSantori.png');
    }

    private function avatarDataUri(User $user): ?string
    {
        $path = trim((string) $user->face_image_path);

        if (
            $path === ''
            || ! Storage::disk('public')->exists($path)
        ) {
            return null;
        }

        $contents = Storage::disk('public')->get($path);

        if ($contents === '') {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path)
            ?: 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function publicImageDataUri(string $relativePath): string
    {
        $path = public_path($relativePath);

        if (! is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return '';
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function whiteMaxsimLogoDataUri(): string
    {
        if ($this->whiteMaxsimLogoDataUri !== null) {
            return $this->whiteMaxsimLogoDataUri;
        }

        $path = public_path('icons/logoMaxSim.png');

        if (
            ! is_file($path)
            || ! function_exists('imagecreatefrompng')
            || ! function_exists('imagepng')
        ) {
            return $this->whiteMaxsimLogoDataUri = $this->publicImageDataUri('icons/logoMaxSim.png');
        }

        $source = @imagecreatefrompng($path);

        if ($source === false) {
            return $this->whiteMaxsimLogoDataUri = $this->publicImageDataUri('icons/logoMaxSim.png');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($source, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;

                if ($alpha < 126) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            imagedestroy($source);

            return $this->whiteMaxsimLogoDataUri = $this->publicImageDataUri('icons/logoMaxSim.png');
        }

        $cropWidth = $maxX - $minX + 1;
        $cropHeight = $maxY - $minY + 1;
        $whiteLogo = imagecreatetruecolor($cropWidth, $cropHeight);

        if ($whiteLogo === false) {
            imagedestroy($source);

            return $this->whiteMaxsimLogoDataUri = $this->publicImageDataUri('icons/logoMaxSim.png');
        }

        imagealphablending($whiteLogo, false);
        imagesavealpha($whiteLogo, true);

        $alphaColors = [];

        for ($y = 0; $y < $cropHeight; $y++) {
            for ($x = 0; $x < $cropWidth; $x++) {
                $rgba = imagecolorat($source, $x + $minX, $y + $minY);
                $alpha = ($rgba >> 24) & 0x7F;
                $alphaColors[$alpha] ??= imagecolorallocatealpha($whiteLogo, 255, 255, 255, $alpha);
                imagesetpixel($whiteLogo, $x, $y, $alphaColors[$alpha]);
            }
        }

        ob_start();
        imagepng($whiteLogo);
        $contents = ob_get_clean();

        imagedestroy($source);
        imagedestroy($whiteLogo);

        if (! is_string($contents) || $contents === '') {
            return $this->whiteMaxsimLogoDataUri = $this->publicImageDataUri('icons/logoMaxSim.png');
        }

        return $this->whiteMaxsimLogoDataUri = 'data:image/png;base64,' . base64_encode($contents);
    }

    private function positionLabel(?string $role): string
    {
        return match ($role) {
            'admin' => 'Quản trị viên',
            'manager' => 'Quản lý',
            'cashier' => 'Thu ngân',
            'staff' => 'Phục vụ',
            default => 'Nhân sự',
        };
    }

    private function positionEnglishLabel(?string $role): string
    {
        return match ($role) {
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'cashier' => 'Cashier',
            'staff' => 'Waiter',
            default => 'Staff',
        };
    }

    private function formatMaskedPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (strlen($digits) >= 9) {
            $prefix = substr($digits, 0, 2);

            return $prefix . 'xx-xxx-xxx';
        }

        return '09xx-xxx-xxx';
    }

    private function branchHotline(?\App\Models\Branch $branch): string
    {
        $raw = trim((string) ($branch?->hotline ?? ''));
        if ($raw !== '') {
            return $raw;
        }

        return '09xx-xxx-xxx';
    }
}
