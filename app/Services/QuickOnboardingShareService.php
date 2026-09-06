<?php

namespace App\Services;

use App\Models\QuickOnboardingEntry;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QuickOnboardingShareService
{
    public function options(QuickOnboardingEntry $entry): array
    {
        $url = $entry->invite_url;

        return [
            'url' => $url,
            'qr_url' => route('quick-onboarding.entries.qr', $entry, absolute: false),
            'zalo_url' => $this->zaloShareUrl($url),
            'messenger_url' => $this->messengerShareUrl($url),
        ];
    }

    public function qrSvg(QuickOnboardingEntry $entry, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $qr = (new Writer($renderer))->writeString($entry->invite_url, ecLevel: ErrorCorrectionLevel::M());
        $qr = preg_replace('/<\?xml[^>]*>\s*/', '', $qr) ?? $qr;
        $height = $size + 54;
        $labelY = $size + 34;
        $code = htmlspecialchars($entry->demo_code ?: '---', ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$height}" viewBox="0 0 {$size} {$height}">
    <rect width="{$size}" height="{$height}" fill="#ffffff"/>
    <svg x="0" y="0" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
        {$qr}
    </svg>
    <text x="50%" y="{$labelY}" text-anchor="middle" font-family="Arial, sans-serif" font-size="22" font-weight="700" fill="#111827">Mã: {$code}</text>
</svg>
SVG;
    }

    private function zaloShareUrl(string $url): string
    {
        return 'https://zalo.me/share?u=' . rawurlencode($url);
    }

    private function messengerShareUrl(string $url): string
    {
        return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url);
    }
}
