<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <title>Xem truoc thong tin Nhân sự</title>
    @vite('resources/css/quick-onboarding-public.css')
</head>
<body>
    @php
        $avatarPath = $data['avatar_path'] ?? $data['face_image_path'] ?? null;
        $cccdImagePath = $data['cccd_image_path'] ?? $data['citizen_id_image_path'] ?? null;
        $avatarUrl = null;
        $cccdImageUrl = null;
        $resolveImageUrl = function (?string $path): ?string {
            $path = trim((string) $path);

            if ($path === '') {
                return null;
            }

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:image')) {
                return $path;
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
                if (is_file($localPath)) {
                    $mime = mime_content_type($localPath) ?: 'image/jpeg';
                    $contents = file_get_contents($localPath);

                    if ($contents !== false) {
                        return 'data:' . $mime . ';base64,' . base64_encode($contents);
                    }
                }
            }

            if (str_starts_with($path, '/storage/')) {
                return asset(ltrim($path, '/'));
            }

            if (str_starts_with($path, 'storage/')) {
                return asset($path);
            }

            return null;
        };

        $avatarUrl = $resolveImageUrl($avatarPath);
        $cccdImageUrl = $resolveImageUrl($cccdImagePath);
    @endphp

    <main class="page">
        <section class="card">
            <div class="preview-top-note">
                Day la ban hien thi mau de ban kiem tra thong tin truoc khi xac nhan.
            </div>

            <div class="top">
                <div class="booking-user">
                    <div class="booking-title">Nhan su</div>

                    <div class="booking-profile booking-profile-preview">
                        <div class="avatar booking-avatar-preview">
                            @if ($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="Anh dai dien nhan su nhan su" onerror="this.remove(); this.nextElementSibling.hidden=false;">
                            @endif
                            <div class="avatar-fallback" @if ($avatarUrl) hidden @endif>{{ mb_strtoupper(mb_substr($data['name'] ?? 'N', 0, 1)) }}</div>
                        </div>

                        <div class="booking-info">
                            <div class="booking-display-name">{{ $data['name'] ?? 'Chua co ten' }}</div>

                            <div class="booking-display-list">
                                <div class="booking-display-meta">{{ $data['gender'] ?? 'Chua co gioi tinh' }}</div>
                                <div class="booking-display-phone">{{ $data['phone'] ?? 'Chua co so dien thoai' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="confirm-box">
                    <div class="summary-head">
                        <div class="summary-title-row">
                            <h1 class="shop-name">{{ $entry->branch?->name ?? 'MAXSIM' }}</h1>
                            <span class="status">Cho xac nhan</span>
                        </div>
                    </div>

                    <div class="summary-info-list" aria-label="Thong tin tom tat nhan su">
                        <div class="summary-item">
                            <span class="summary-label">Chuc vu</span>
                            <strong class="summary-value">{{ ['manager' => 'Quan ly', 'staff' => 'Nhan vien', 'cashier' => 'Thu ngan'][$entry->intended_role] ?? $entry->intended_role }}</strong>
                        </div>

                        <div class="summary-item">
                            <span class="summary-label">CCCD</span>
                            <strong class="summary-value">{{ $data['citizen_id'] ?? 'Chua co' }}</strong>
                        </div>

                        <div class="summary-item">
                            <span class="summary-label">Anh CCCD</span>
                            @if ($cccdImageUrl)
                                <a class="summary-value" href="{{ $cccdImageUrl }}" target="_blank" rel="noopener">Da tai anh CCCD</a>
                            @else
                                <strong class="summary-value">Chua co</strong>
                            @endif
                        </div>

                        <div class="summary-item">
                            <span class="summary-label">Ngay sinh</span>
                            <strong class="summary-value is-time">{{ $data['date_of_birth'] ?? 'Chua co' }}</strong>
                        </div>

                        <div class="summary-item">
                            <span class="summary-label">Email</span>
                            <strong class="summary-value">{{ $data['email'] ?? 'Khong co' }}</strong>
                        </div>

                        <div class="summary-item">
                            <span class="summary-label">Dia chi</span>
                            <strong class="summary-value is-address">{{ $data['address'] ?? 'Chua co' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="preview-actions">
                    <a class="back-btn preview-back-link" href="{{ route('quick-onboarding.public.show', $entry->token) }}">
                        Quay lai sua
                    </a>

                    <form method="POST" action="{{ route('quick-onboarding.public.confirm', $entry->token) }}">
                        @csrf
                        <button class="confirm-btn save-confirm-btn" id="saveConfirmBtn" type="submit">
                            <span id="saveConfirmText">Xac nhan</span>
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
