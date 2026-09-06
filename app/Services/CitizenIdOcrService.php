<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CitizenIdOcrService
{
    private bool $lastOk = false;

    private string $lastMessage = 'PaddleOCR chua san sang hoac chua duoc cai dat.';

    public function extract(UploadedFile $image): array
    {
        $this->lastOk = false;

        if (!$this->isConfigured()) {
            $this->lastMessage = 'PaddleOCR chua san sang hoac chua duoc cai dat.';

            return $this->emptyResult();
        }

        $originalImagePath = $this->stripPathQuotes((string) $image->getRealPath());
        $rawSavedPath = '';

        try {
            $preparedImage = $this->prepareImageForOcr($image);
        } catch (\Throwable $exception) {
            $this->lastMessage = $this->debugMessage($exception->getMessage());
            Log::warning('Quick onboarding PaddleOCR could not normalize uploaded image.', [
                'uploaded_original_name' => $image->getClientOriginalName(),
                'uploaded_mime_type' => $image->getClientMimeType(),
                'uploaded_size' => $image->getSize(),
                'image_original_tmp_path' => $originalImagePath,
                'exception_message' => $exception->getMessage(),
            ]);

            return $this->emptyResult();
        }

        $rawSavedPath = $preparedImage['raw_saved_path'];
        $imagePath = $this->canonicalPath($preparedImage['normalized_ocr_path']);

        if (!is_file($imagePath) || filesize($imagePath) <= 0) {
            $technicalMessage = 'OCR image path not found or empty: ' . $imagePath;
            $this->lastMessage = $this->debugMessage($technicalMessage);
            Log::warning('Quick onboarding HTTP OCR normalized image is missing.', $this->ocrDebugContext($image, $originalImagePath, $rawSavedPath, $imagePath));
            $this->cleanupTemporaryFiles([$rawSavedPath, $imagePath]);

            return $this->emptyResult();
        }

        try {
            $imageContents = file_get_contents($imagePath);

            if ($imageContents === false) {
                $this->lastMessage = 'OCR chua doc duoc du lieu, vui long nhap bo sung.';
                Log::warning('Quick onboarding HTTP OCR could not read normalized image.', $this->ocrDebugContext($image, $originalImagePath, $rawSavedPath, $imagePath));

                return $this->emptyResult();
            }

            $response = Http::acceptJson()
                ->connectTimeout(10)
                ->timeout((int) config('services.ocr.timeout', 120))
                ->attach('image', $imageContents, basename($imagePath))
                ->post((string) config('services.ocr.service_url'));

            $payload = $response->json();
            $contentType = $response->header('Content-Type');

            if (!$response->successful()) {
                $this->lastMessage = 'OCR chua doc duoc du lieu, vui long nhap bo sung.';
                Log::warning('Quick onboarding HTTP OCR request failed.', $this->ocrDebugContext($image, $originalImagePath, $rawSavedPath, $imagePath, [
                    'http_status' => $response->status(),
                    'response_content_type' => $contentType,
                    'response_body_preview' => Str::limit($response->body(), 1000, ''),
                    'ocr_service_message' => is_array($payload) ? ($payload['message'] ?? null) : null,
                    'ocr_service_error_type' => is_array($payload) ? ($payload['error_type'] ?? null) : null,
                ]));

                return $this->emptyResult();
            }

            if (!is_array($payload)) {
                $technicalMessage = 'OCR service khong tra ve JSON hop le.';
                $this->lastMessage = $this->debugMessage($technicalMessage);
                Log::warning('Quick onboarding HTTP OCR returned invalid JSON.', $this->ocrDebugContext($image, $originalImagePath, $rawSavedPath, $imagePath, [
                    'http_status' => $response->status(),
                    'response_content_type' => $contentType,
                    'response_body_preview' => Str::limit($response->body(), 1000, ''),
                ]));

                return $this->emptyResult();
            }

            if (($payload['ok'] ?? false) !== true) {
                $this->lastMessage = 'OCR chua doc duoc du lieu, vui long nhap bo sung.';
                Log::warning('Quick onboarding HTTP OCR returned error.', $this->ocrDebugContext($image, $originalImagePath, $rawSavedPath, $imagePath, [
                    'http_status' => $response->status(),
                    'response_content_type' => $contentType,
                    'response_body_preview' => Str::limit($response->body(), 1000, ''),
                    'ocr_service_message' => $payload['message'] ?? null,
                    'ocr_service_error_type' => $payload['error_type'] ?? null,
                ]));

                return $this->emptyResult();
            }

            $rawText = trim((string) ($payload['raw_text'] ?? ''));

            if ($rawText === '') {
                $this->lastMessage = 'OCR chua doc duoc noi dung anh, vui long nhap bo sung.';

                return $this->emptyResult();
            }

            $data = $this->parseRawText($rawText);
            $this->lastOk = $this->hasImportantData($data);
            $this->lastMessage = $this->lastOk
                ? 'Da doc thong tin CCCD.'
                : 'OCR da doc anh nhung chua nhan dien duoc thong tin CCCD, vui long nhap bo sung.';

            Log::info('Quick onboarding HTTP OCR parsed image.', [
                'raw_text_length' => mb_strlen($rawText),
                'has_important_data' => $this->lastOk,
                'extracted_fields' => array_keys(array_filter(
                    $data,
                    static fn ($value, string $key): bool => $key !== 'raw_text' && trim((string) $value) !== '',
                    ARRAY_FILTER_USE_BOTH
                )),
            ]);

            return $data;
        } catch (\Throwable $exception) {
            $this->lastMessage = 'OCR chua doc duoc du lieu, vui long nhap bo sung.';
            Log::warning('Quick onboarding HTTP OCR failed.', $this->ocrDebugContext($image, $originalImagePath, $rawSavedPath, $imagePath, [
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]));

            return $this->emptyResult();
        } finally {
            $this->cleanupTemporaryFiles([$rawSavedPath, $imagePath]);
        }
    }

    public function parseRawText(string $rawText): array
    {
        $text = $this->normalizeText($rawText);
        $lines = $this->normalizedLines($text);

        $data = array_merge($this->emptyResult(), [
            'citizen_id' => $this->extractCitizenId($text),
            'name' => $this->extractName($lines),
            'date_of_birth' => $this->normalizeDate($this->extractDateAfterLabels($lines, [
                'ngay sinh',
                'date of birth',
            ])),
            'gender' => $this->extractGender($lines),
            'place_of_origin' => $this->extractBlockAfterLabels(
                $lines,
                ['place of origin', 'que quan'],
                ['noi thuong tru', 'thuong tru', 'place of residence', 'co gia tri den', 'date of expiry', 'dac diem nhan dang', 'ngay cap'],
                2
            ),
            'address' => $this->extractAddress($lines),
            'issue_date' => $this->extractIssueDate($lines),
            'raw_text' => $text,
        ]);

        return $this->cleanParsedData($data);
    }

    public function emptyResult(): array
    {
        return [
            'name' => '',
            'citizen_id' => '',
            'date_of_birth' => '',
            'gender' => '',
            'place_of_origin' => '',
            'address' => '',
            'issue_date' => '',
            'raw_text' => '',
        ];
    }

    public function isStub(): bool
    {
        return !$this->isConfigured();
    }

    public function lastOk(): bool
    {
        return $this->lastOk;
    }

    public function lastMessage(): string
    {
        return $this->lastMessage;
    }

    private function isConfigured(): bool
    {
        return (bool) config('services.ocr.enabled')
            && config('services.ocr.provider') === 'paddleocr_http'
            && trim((string) config('services.ocr.service_url')) !== '';
    }

    private function prepareImageForOcr(UploadedFile $image): array
    {
        $rawDirectory = storage_path('app/quick-onboarding/ocr-temp');
        $normalizedDirectory = storage_path('app/quick-onboarding/ocr-normalized');
        File::ensureDirectoryExists($rawDirectory);
        File::ensureDirectoryExists($normalizedDirectory);

        $extension = strtolower((string) ($image->extension() ?: $image->guessExtension() ?: 'jpg'));
        $extension = match ($extension) {
            'jpg', 'jpeg', 'png', 'webp' => $extension,
            default => 'jpg',
        };

        $uuid = Str::uuid()->toString();
        $rawSavedPath = $this->stripPathQuotes($rawDirectory . DIRECTORY_SEPARATOR . $uuid . '.' . $extension);
        $normalizedPath = $this->stripPathQuotes($normalizedDirectory . DIRECTORY_SEPARATOR . $uuid . '.jpg');

        File::copy((string) $image->getRealPath(), $rawSavedPath);

        $imageData = file_get_contents($rawSavedPath);
        $source = $imageData === false ? false : imagecreatefromstring($imageData);

        if ($source === false) {
            throw new \RuntimeException('Cannot read uploaded OCR image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        try {
            if (!imagejpeg($canvas, $normalizedPath, 92)) {
                throw new \RuntimeException('Cannot write normalized OCR image.');
            }
        } finally {
            imagedestroy($canvas);
            imagedestroy($source);
        }

        return [
            'raw_saved_path' => $rawSavedPath,
            'normalized_ocr_path' => realpath($normalizedPath) ?: $normalizedPath,
        ];
    }

    private function cleanupTemporaryFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $path = $this->stripPathQuotes((string) $path);

            if ($path === '' || !is_file($path)) {
                continue;
            }

            try {
                File::delete($path);
            } catch (\Throwable $exception) {
                Log::notice('Could not delete temporary CCCD OCR image.', [
                    'path' => $path,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function stripPathQuotes(string $path): string
    {
        return trim($path, " \t\n\r\0\x0B\"'");
    }

    private function canonicalPath(string $path): string
    {
        $path = $this->stripPathQuotes($path);

        return realpath($path) ?: $path;
    }

    private function ocrDebugContext(UploadedFile $image, string $originalImagePath, string $rawSavedPath, string $ocrImagePath, array $extra = []): array
    {
        return array_merge([
            'ocr_provider' => config('services.ocr.provider'),
            'ocr_service_url' => config('services.ocr.service_url'),
            'uploaded_original_name' => $image->getClientOriginalName(),
            'uploaded_mime_type' => $image->getClientMimeType(),
            'uploaded_size' => $image->getSize(),
            'image_original_tmp_path' => $originalImagePath,
            'raw_saved_path' => $rawSavedPath,
            'raw_saved_path_exists' => is_file($rawSavedPath),
            'image_ocr_path' => $ocrImagePath,
            'image_ocr_path_exists' => is_file($ocrImagePath),
            'normalized_ocr_path' => $ocrImagePath,
            'normalized_ocr_path_exists' => is_file($ocrImagePath),
            'normalized_ocr_path_size' => is_file($ocrImagePath) ? filesize($ocrImagePath) : null,
            'working_directory' => base_path(),
        ], $extra);
    }

    private function debugMessage(string $technicalMessage): string
    {
        if (app()->environment(['local', 'development', 'dev'])) {
            return $technicalMessage;
        }

        return 'PaddleOCR chua san sang hoac chua duoc cai dat.';
    }

    private function normalizeText(string $rawText): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $rawText);
        $text = str_replace(["“", "”", "‘", "’", "|"], ['"', '"', "'", "'", ' '], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{2,}/u', "\n", $text) ?? $text;

        return trim($text);
    }

    private function normalizedLines(string $text): array
    {
        return array_values(array_filter(
            array_map(fn (string $line): string => $this->cleanValue($line), explode("\n", $text)),
            static fn (string $line): bool => $line !== ''
        ));
    }

    private function extractCitizenId(string $text): string
    {
        $lines = $this->normalizedLines($text);
        $sources = [];

        foreach ($lines as $index => $line) {
            $normalized = $this->normalizeForMatch($line);

            if (preg_match('/\b(?:so|no)\b/u', $normalized) === 1) {
                $sources[] = $line . ' ' . ($lines[$index + 1] ?? '');
            }
        }

        $sources[] = $text;

        foreach ($sources as $source) {
            preg_match_all('/(?<![0-9A-Za-z])(?:[0-9OoIl][\s.\-]*){12}(?![0-9A-Za-z])/u', $source, $matches);

            foreach ($matches[0] ?? [] as $candidate) {
                $number = strtr($candidate, [
                    'O' => '0',
                    'o' => '0',
                    'I' => '1',
                    'l' => '1',
                ]);
                $number = preg_replace('/\D/u', '', $number) ?? '';

                if (strlen($number) === 12) {
                    return $number;
                }
            }
        }

        return '';
    }

    private function extractName(array $lines): string
    {
        $value = $this->extractInlineOrNext($lines, [
            'ho va ten',
            'full name',
        ]);

        if ($value === '' || $this->isTitleLine($value) || $this->isStopLine($value)) {
            return '';
        }

        return $this->cleanPersonName($value);
    }

    private function cleanPersonName(string $value): string
    {
        $value = $this->stripLeadingKnownLabels($value);
        $value = preg_replace('/[^\p{L}\s\-]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        if ($value === '' || preg_match('/\d/u', $value) === 1) {
            return '';
        }

        $words = preg_split('/\s+/u', $value) ?: [];

        if (count($words) < 2 || count($words) > 7) {
            return '';
        }

        return mb_strtoupper($value, 'UTF-8');
    }

    private function extractGender(array $lines): string
    {
        $value = $this->extractInlineOrNext($lines, [
            'gioi tinh',
            'gioitinh',
            'giditinh',
            'sex',
        ]);

        $value = $this->truncateAtLabels($value, [
            'quoc tich',
            'nationality',
            'que quan',
            'place of origin',
        ]);

        return $this->normalizeGender($value);
    }

    private function extractIssueDate(array $lines): string
    {
        foreach ($lines as $index => $line) {
            $matchedLabel = $this->matchingLabel($line, [
                'ngay cap',
                'date of issue',
            ]);

            if ($matchedLabel === null) {
                continue;
            }

            $inline = $this->valueAfterLabel($line, $matchedLabel);
            $date = $this->firstDateInText($inline);

            if ($date !== '') {
                return $date;
            }

            $checked = 0;

            for ($next = $index + 1; $next < count($lines) && $checked < 3; $next++) {
                $candidate = $this->cleanValue($lines[$next]);

                if ($candidate === '' || $this->isNoiseLine($candidate) || $this->containsAnyLabel($candidate, [
                    'co gia tri den',
                    'date of expiry',
                    'of expiry',
                    'expiry',
                ])) {
                    continue;
                }

                $checked++;
                $date = $this->firstDateInText($candidate);

                if ($date !== '') {
                    return $date;
                }

                if ($this->isHardAddressStopLine($candidate) || $this->isStopLine($candidate)) {
                    break;
                }
            }
        }

        return '';
    }

    private function extractDateAfterLabels(array $lines, array $labels): string
    {
        $value = $this->extractInlineOrNext($lines, $labels);
        $value = strtr($value, ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1']);

        if (preg_match('/\b\d{1,2}\s*[\/\-.]\s*\d{1,2}\s*[\/\-.]\s*\d{4}\b/u', $value, $matches)) {
            return preg_replace('/\s+/u', '', $matches[0]) ?? $matches[0];
        }

        if (preg_match('/\b\d{4}\s*[\/\-.]\s*\d{1,2}\s*[\/\-.]\s*\d{1,2}\b/u', $value, $matches)) {
            return preg_replace('/\s+/u', '', $matches[0]) ?? $matches[0];
        }

        return '';
    }

    private function extractSingleLineAfterLabels(array $lines, array $labels): string
    {
        $value = $this->extractInlineOrNext($lines, $labels);

        return $this->isStopLine($value) || $this->isTitleLine($value) ? '' : $value;
    }

    private function extractInlineOrNext(array $lines, array $labels): string
    {
        foreach ($lines as $index => $line) {
            $normalizedLine = $this->normalizeForMatch($line);

            foreach ($labels as $label) {
                $normalizedLabel = $this->normalizeForMatch($label);

                if ($normalizedLabel === '' || !str_contains($normalizedLine, $normalizedLabel)) {
                    continue;
                }

                $value = $this->valueAfterLabel($line, $label);

                if ($value !== '' && !$this->isLabelOnlyValue($value)) {
                    return $value;
                }

                for ($next = $index + 1; $next < count($lines); $next++) {
                    $candidate = $this->cleanValue($lines[$next]);

                    if ($candidate === '' || $this->isTitleLine($candidate) || $this->isNoiseLine($candidate)) {
                        continue;
                    }

                    if ($this->isStopLine($candidate)) {
                        return '';
                    }

                    return $candidate;
                }
            }
        }

        return '';
    }

    private function extractBlockAfterLabels(
        array $lines,
        array $startLabels,
        array $stopLabels,
        int $maxParts = 3,
        array $skipLabels = []
    ): string {
        foreach ($lines as $index => $line) {
            $matchedLabel = $this->matchingLabel($line, $startLabels);

            if ($matchedLabel === null) {
                continue;
            }

            $parts = [];
            $inline = $this->truncateAtLabels($this->valueAfterLabel($line, $matchedLabel), $stopLabels);

            if (
                $inline !== ''
                && !$this->isNoiseLine($inline)
                && !$this->containsAnyLabel($inline, $startLabels)
                && !$this->containsAnyLabel($inline, $skipLabels)
            ) {
                $parts[] = $inline;
            }

            for ($next = $index + 1; $next < count($lines); $next++) {
                $candidate = $this->cleanValue($lines[$next]);

                if ($candidate === '' || $this->isTitleLine($candidate) || $this->isNoiseLine($candidate)) {
                    continue;
                }

                if ($this->containsAnyLabel($candidate, $stopLabels)) {
                    break;
                }

                if ($this->containsAnyLabel($candidate, $skipLabels) || $this->looksLikeDate($candidate)) {
                    continue;
                }

                $candidate = $this->truncateAtLabels($candidate, $stopLabels);

                if ($candidate === '' || $this->isNoiseLine($candidate)) {
                    continue;
                }

                $parts[] = $candidate;

                if (count($parts) >= $maxParts) {
                    break;
                }
            }

            return $this->cleanAddressValue(implode(', ', array_values(array_unique($parts))));
        }

        return '';
    }

    private function extractAddress(array $lines): string
    {
        return $this->extractBlockAfterLabels(
            $lines,
            ['noi thuong tru', 'thuong tru', 'place of residence'],
            ['dac diem nhan dang', 'personal identification', 'ngay cap', 'date of issue', 'can cuoc dien tu', 'lich su cap the'],
            3,
            ['co gia tri den', 'date of expiry', 'of expiry', 'expiry']
        );
    }

    private function matchingLabel(string $line, array $labels): ?string
    {
        $normalizedLine = $this->normalizeForMatch($line);

        foreach ($labels as $label) {
            $normalizedLabel = $this->normalizeForMatch($label);

            if ($normalizedLabel !== '' && str_contains($normalizedLine, $normalizedLabel)) {
                return $label;
            }
        }

        return null;
    }

    private function containsAnyLabel(string $line, array $labels): bool
    {
        return $this->matchingLabel($line, $labels) !== null;
    }

    private function truncateAtLabels(string $value, array $labels): string
    {
        $result = $this->cleanValue($value);

        foreach ($labels as $label) {
            $pattern = $this->labelRegex($label);

            if ($pattern === '') {
                continue;
            }

            $result = preg_split('/\s*(?:\/|\||-|–)?\s*' . $pattern . '\b/iu', $result, 2)[0] ?? $result;
        }

        return $this->cleanValue($result);
    }

    private function valueAfterLabel(string $line, string $label): string
    {
        $pattern = $this->labelRegex($label);

        if ($pattern === '') {
            return '';
        }

        $remaining = preg_replace(
            '/^.*?' . $pattern . '\s*(?:\/\s*)?\s*[:;\-.]?\s*/iu',
            '',
            $line,
            1,
            $count
        );

        if ($count !== 1 || $remaining === null || $remaining === $line) {
            return '';
        }

        $remaining = $this->stripLeadingKnownLabels($remaining);
        $remaining = $this->cleanValue($remaining);

        return $this->isLabelOnlyValue($remaining) ? '' : $remaining;
    }

    private function stripLeadingKnownLabels(string $value): string
    {
        $patterns = [
            'ho va ten',
            'full name',
            'ngay sinh',
            'date of birth',
            'gioi tinh',
            'sex',
            'quoc tich',
            'nationality',
            'que quan',
            'place of origin',
            'noi thuong tru',
            'thuong tru',
            'place of residence',
            'ngay cap',
            'date of issue',
            'co gia tri den',
            'date of expiry',
        ];

        $result = $value;

        for ($round = 0; $round < 3; $round++) {
            $changed = false;

            foreach ($patterns as $label) {
                $pattern = $this->labelRegex($label);
                $next = preg_replace('/^\s*(?:\/|\||-|–)?\s*' . $pattern . '\s*[:;\-.]?\s*/iu', '', $result, 1, $count);

                if ($count === 1 && $next !== null && $next !== $result) {
                    $result = $next;
                    $changed = true;
                }
            }

            if (!$changed) {
                break;
            }
        }

        return $this->cleanValue($result);
    }

    private function labelRegex(string $label): string
    {
        return match ($this->normalizeForMatch($label)) {
            'ho va ten' => '(?:h[oọ]\s*v[aà]\s*t[eêé]n|ho\s+va\s+ten)',
            'ngay sinh' => '(?:ng[aà]y\s*s[ií]?nh|ngay\s+sinh)',
            'gioi tinh' => '(?:gi[oơớ]i\s*t[ií]nh|gioi\s+tinh)',
            'gioitinh' => '(?:gi[oơớ]i\s*t[ií]nh|gioitinh)',
            'giditinh' => '(?:giditinh|gidi\s*tinh)',
            'quoc tich' => '(?:qu[oốô0]c\s*t[ií]ch|quoc\s+tich)',
            'que quan' => '(?:qu[eêé]\s*qu[aá]n|que\s+quan)',
            'noi thuong tru' => '(?:n[oơ]i\s*th[uư][oơờ]ng\s*tr[uú]|noi\s+thuong\s+tru)',
            'thuong tru' => '(?:th[uư][oơờ]ng\s*tr[uú]|thuong\s+tru)',
            'ngay cap' => '(?:ng[aà]y\s*c[aấ]p|ngay\s+cap)',
            'co gia tri den' => '(?:c[oó]\s*gi[aá]\s*tr[iị]\s*[dđ]e[nế]|co\s+gia\s+tri\s+den)',
            default => preg_quote($label, '/'),
        };
    }

    private function normalizeForMatch(string $value): string
    {
        $value = mb_strtolower($this->cleanValue($value), 'UTF-8');

        // Chuẩn hóa tiếng Việt thủ công trước khi gọi iconv.
        // iconv trên Windows và Linux có thể cho kết quả khác nhau với Đ/đ
        // và một số ký tự có dấu, khiến parser không nhận ra nhãn.
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ü' => 'u',
            'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
            'đ' => 'd',
        ]);

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    private function cleanValue(string $value): string
    {
        $value = preg_replace('/[ \t]*\n[ \t]*/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B:;|~_\"'“”");
    }

    private function cleanAddressValue(string $value): string
    {
        $value = $this->stripKnownLabelsEverywhere($this->cleanValue($value));
        $value = preg_replace('/\s+,/u', ',', $value) ?? $value;
        $value = preg_replace('/,\s*,+/u', ', ', $value) ?? $value;
        $value = preg_replace('/,\s*/u', ', ', $value) ?? $value;

        return trim($value, " ,;:-");
    }

    private function cleanParsedData(array $data): array
    {
        foreach (['place_of_origin', 'address'] as $field) {
            $data[$field] = $this->cleanAddressValue((string) ($data[$field] ?? ''));
        }

        foreach (['name', 'citizen_id', 'date_of_birth', 'gender', 'issue_date'] as $field) {
            $data[$field] = $this->cleanValue((string) ($data[$field] ?? ''));
        }

        return $data;
    }

    private function stripKnownLabelsEverywhere(string $value): string
    {
        $labels = [
            'ho va ten',
            'full name',
            'ngay sinh',
            'date of birth',
            'gioi tinh',
            'sex',
            'quoc tich',
            'nationality',
            'que quan',
            'place of origin',
            'noi thuong tru',
            'thuong tru',
            'place of residence',
            'co gia tri den',
            'date of expiry',
            'of expiry',
            'expiry',
            'dac diem nhan dang',
            'personal identification',
            'ngay cap',
            'date of issue',
        ];

        $result = $value;

        foreach ($labels as $label) {
            $pattern = $this->labelRegex($label);

            if ($pattern === '') {
                continue;
            }

            $result = preg_replace('/(?:^|[\s,;:\/\|I]+)' . $pattern . '\s*[:;\-.]?\s*/iu', ' ', $result) ?? $result;
        }

        $result = preg_replace('/\s*[\/\|]+\s*/u', ' ', $result) ?? $result;
        $result = preg_replace('/\s*,\s*/u', ', ', $result) ?? $result;
        $result = preg_replace('/(?:,\s*){2,}/u', ', ', $result) ?? $result;
        $result = preg_replace('/\s+/u', ' ', $result) ?? $result;

        return trim($result, " \t\n\r\0\x0B,;:.-/|");
    }

    private function isNoiseLine(string $line): bool
    {
        $normalized = $this->normalizeForMatch($line);

        if ($normalized === '' || strlen($normalized) < 3) {
            return true;
        }

        if (!str_contains($normalized, ' ') && strlen($normalized) <= 3) {
            return true;
        }

        if (preg_match('/^(?:of\s+expiry|te\s+of\s+expiry|date\s+of\s+expiry)$/u', $normalized) === 1) {
            return true;
        }

        foreach ([
            'cong hoa xa hoi chu nghia viet nam',
            'socialist republic of viet nam',
            'doc lap tu do hanh phuc',
            'independence freedom happiness',
            'can cuoc cong dan',
            'citizen identity card',
        ] as $noise) {
            if (str_contains($normalized, $noise)) {
                return true;
            }
        }

        return false;
    }

    private function isTitleLine(string $line): bool
    {
        $normalized = $this->normalizeForMatch($line);

        foreach ([
            'thong tin the can cuoc cong dan',
            'can cuoc cong dan',
            'citizen identity card',
            'can cuoc dien tu',
            'lich su cap the',
        ] as $title) {
            if (str_contains($normalized, $title)) {
                return true;
            }
        }

        return false;
    }

    private function isLabelOnlyValue(string $value): bool
    {
        $normalized = $this->normalizeForMatch($value);

        foreach ([
            'full name',
            'date of birth',
            'sex',
            'nationality',
            'place of origin',
            'place of residence',
            'date of issue',
            'date of expiry',
        ] as $label) {
            if ($normalized === $this->normalizeForMatch($label)) {
                return true;
            }
        }

        return false;
    }

    private function isStopLine(string $line): bool
    {
        return $this->containsAnyLabel($line, [
            'ho va ten',
            'full name',
            'ngay sinh',
            'date of birth',
            'gioi tinh',
            'gioitinh',
            'giditinh',
            'sex',
            'quoc tich',
            'nationality',
            'que quan',
            'place of origin',
            'noi thuong tru',
            'thuong tru',
            'place of residence',
            'ngay cap',
            'date of issue',
            'dac diem nhan dang',
            'personal identification',
        ]) || $this->isTitleLine($line);
    }

    private function isSkippableAddressLine(string $line): bool
    {
        return $this->containsAnyLabel($line, [
            'co gia tri den',
            'date of expiry',
            'of expiry',
            'expiry',
        ]) || $this->isNoiseLine($line);
    }

    private function isHardAddressStopLine(string $line): bool
    {
        return $this->containsAnyLabel($line, [
            'dac diem nhan dang',
            'personal identification',
            'ngay cap',
            'date of issue',
            'can cuoc dien tu',
            'lich su cap the',
        ]);
    }

    private function looksLikeDate(string $line): bool
    {
        $line = strtr($line, ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1']);

        return preg_match('/^\s*[\(\[]?\d{1,2}\s*[\/\-.]\s*\d{1,2}\s*[\/\-.]\s*\d{4}[\)\]]?\s*$/u', $line) === 1
            || preg_match('/^\s*[\(\[]?\d{4}\s*[\/\-.]\s*\d{1,2}\s*[\/\-.]\s*\d{1,2}[\)\]]?\s*$/u', $line) === 1;
    }

    private function normalizeDate(string $value): string
    {
        $value = strtr($this->cleanValue($value), ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1']);

        if (preg_match('/\b(\d{1,2})\s*[\/\-.]\s*(\d{1,2})\s*[\/\-.]\s*(\d{4})\b/u', $value, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];

            return checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : '';
        }

        if (preg_match('/\b(\d{4})\s*[\/\-.]\s*(\d{1,2})\s*[\/\-.]\s*(\d{1,2})\b/u', $value, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            return checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : '';
        }

        return '';
    }

    private function firstDateInText(string $value): string
    {
        $value = strtr($this->cleanValue($value), ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1']);

        if (preg_match('/\b\d{1,2}\s*[\/\-.]\s*\d{1,2}\s*[\/\-.]\s*\d{4}\b/u', $value, $matches)) {
            return $this->normalizeDate($matches[0]);
        }

        if (preg_match('/\b\d{4}\s*[\/\-.]\s*\d{1,2}\s*[\/\-.]\s*\d{1,2}\b/u', $value, $matches)) {
            return $this->normalizeDate($matches[0]);
        }

        return '';
    }

    private function normalizeGender(string $value): string
    {
        $normalized = $this->normalizeForMatch($value);

        if (preg_match('/(?:^|\s)(?:nu|female)(?:\s|quoc|nationality|que|place|$)/u', $normalized) === 1) {
            return 'Nu';
        }

        if (preg_match('/(?:^|\s)(?:nam|male)(?:\s|quoc|nationality|que|place|$)/u', $normalized) === 1) {
            return 'Nam';
        }

        return '';
    }

    private function hasImportantData(array $data): bool
    {
        if (preg_match('/^\d{12}$/', (string) ($data['citizen_id'] ?? '')) === 1) {
            return true;
        }

        $count = 0;

        foreach (['name', 'date_of_birth', 'place_of_origin', 'address', 'issue_date'] as $field) {
            if (trim((string) ($data[$field] ?? '')) !== '') {
                $count++;
            }
        }

        return $count >= 2;
    }
}
