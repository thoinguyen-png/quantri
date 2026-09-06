<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Đánh giá phục vụ | MAXSIM</title>
    @php
    $qrCssUrl = '/css/qr-rating.css?v=' . filemtime(public_path('css/qr-rating.css'));
    $qrJsUrl = '/js/qr-rating.js?v=' . filemtime(public_path('js/qr-rating.js'));
    $qrLogoUrl = '/images/maxsim-logo.png?v=' . filemtime(public_path('images/maxsim-logo.png'));
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700;800;900&display=swap">
    <link rel="preload" as="style" href="{{ $qrCssUrl }}">
    <link rel="stylesheet" href="{{ $qrCssUrl }}">
    <script defer src="{{ $qrJsUrl }}"></script>
    <style>
        /* ================= COMPACT ONE-PAGE RATING OVERRIDE ================= */

        .qr-rating-shell {
            padding: max(10px, env(safe-area-inset-top)) 11px max(12px, env(safe-area-inset-bottom));
        }

        .qr-rating-top {
            padding: 13px 15px 0;
        }

        .qr-rating-logo {
            width: 128px;
            height: 43px;
            flex-basis: 128px;
            border-radius: 15px;
        }

        .qr-rating-hero {
            padding: 11px 16px 5px;
        }

        .qr-rating-hero h1 {
            font-size: clamp(26px, 7vw, 33px);
            line-height: 1.04;
        }

        .qr-rating-hero p {
            margin-top: 4px;
            font-size: 13px;
            line-height: 1.35;
        }

        /* ================= EMPLOYEE AVATAR PREMIUM ================= */

        .qr-employee {
            position: relative;
            display: grid;
            justify-items: center;
            margin: 2px 18px 10px;
            padding: 0;
            border: 0;
            background: transparent;
        }

        .qr-employee::before {
            content: "";
            position: absolute;
            top: 54px;
            left: 50%;
            width: min(100%, 340px);
            height: 120px;
            transform: translateX(-50%);
            border-radius: 999px;
            background:
                radial-gradient(circle at 50% 28%, rgba(255, 255, 255, 0.2), transparent 56%),
                radial-gradient(circle at 50% 82%, rgba(159, 179, 255, 0.16), transparent 64%);
            filter: blur(1px);
            pointer-events: none;
        }

        .qr-employee-avatar {
            position: relative;
            z-index: 2;
            width: 182px;
            height: 182px;
            display: grid;
            place-items: center;
            margin: 0 auto -42px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.42);
            border-radius: 48px;
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.045)),
                rgba(255, 255, 255, 0.1);
            color: var(--qr-text);
            font-size: 58px;
            font-weight: 950;
            box-shadow:
                0 20px 48px rgba(0, 0, 0, 0.46),
                0 0 0 8px rgba(255, 255, 255, 0.05),
                inset 0 1px rgba(255, 255, 255, 0.24);
        }

        .qr-employee-avatar::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            box-shadow:
                inset 0 -28px 36px rgba(0, 0, 0, 0.2),
                inset 0 1px rgba(255, 255, 255, 0.16);
            pointer-events: none;
        }

        .qr-employee-avatar img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .qr-employee-avatar span {
            display: grid;
            place-items: center;
            width: 100%;
            height: 100%;
        }

        .qr-employee-avatar span[hidden] {
            display: none;
        }

        .qr-employee-copy {
            position: relative;
            z-index: 1;
            width: 100%;
            min-width: 0;
            padding: 52px 14px 11px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 25px;
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.105), rgba(255, 255, 255, 0.045)),
                rgba(255, 255, 255, 0.045);
            box-shadow:
                inset 0 1px rgba(255, 255, 255, 0.08),
                0 14px 30px rgba(0, 0, 0, 0.18);
            text-align: center;
        }

        .qr-employee-name {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--qr-text);
            font-size: 22px;
            line-height: 1.05;
            font-weight: 950;
        }

        .qr-employee-meta {
            margin-top: 4px;
            color: var(--qr-muted);
            font-size: 13px;
            line-height: 1.25;
        }

        /* ================= FORM COMPACT ================= */

        .qr-rating-form {
            padding: 0 16px 14px;
        }

        .qr-rating-question {
            margin-bottom: 8px;
            font-size: 15px;
            line-height: 1.1;
        }

        .qr-rating-options {
            gap: 6px;
        }

        .qr-rating-option {
            min-height: 96px;
            padding: 8px 5px;
            border-radius: 17px;
            gap: 6px;
        }

        .qr-rating-icon {
            width: 52px;
            height: 52px;
        }

        .qr-rating-icon svg {
            width: 30px;
            height: 30px;
        }

        .qr-message-field {
            margin: 9px 0 9px;
        }

        .qr-message-head {
            margin-bottom: 6px;
        }

        .qr-textarea {
            min-height: 52px;
            padding: 10px 13px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.35;
        }

        .qr-submit {
            min-height: 52px;
            border-radius: 17px;
            font-size: 15px;
        }

        .qr-footnote {
            margin-top: 7px;
            font-size: 10px;
        }

        @media (max-height: 760px) {
            .qr-rating-shell {
                align-items: start;
            }

            .qr-rating-card {
                border-radius: 24px;
            }

            .qr-rating-top {
                padding-top: 11px;
            }

            .qr-rating-hero {
                padding-top: 10px;
                padding-bottom: 5px;
            }

            .qr-employee {
                margin-bottom: 8px;
            }

            .qr-employee-avatar {
                width: 174px;
                height: 174px;
                margin-bottom: -40px;
                border-radius: 46px;
            }

            .qr-employee-copy {
                padding-top: 50px;
                padding-bottom: 9px;
            }

            .qr-rating-option {
                min-height: 92px;
            }

            .qr-textarea {
                min-height: 50px;
            }
        }

        @media (max-width: 375px) {
            .qr-rating-shell {
                padding-inline: 10px;
            }

            .qr-rating-top {
                padding: 12px 14px 0;
            }

            .qr-rating-logo {
                width: 118px;
                height: 41px;
                flex-basis: 118px;
                border-radius: 14px;
            }

            .qr-rating-pill {
                padding: 7px 9px;
                font-size: 11px;
            }

            .qr-rating-hero h1 {
                font-size: 28px;
            }

            .qr-employee {
                margin-inline: 15px;
                margin-bottom: 11px;
            }

            .qr-employee-avatar {
                width: 168px;
                height: 168px;
                margin-bottom: -38px;
                border-radius: 44px;
            }

            .qr-employee-copy {
                padding: 49px 12px 10px;
                border-radius: 23px;
            }

            .qr-employee-name {
                font-size: 19px;
            }

            .qr-rating-form {
                padding-inline: 13px;
                padding-bottom: 15px;
            }

            .qr-rating-option {
                min-height: 92px;
                border-radius: 17px;
            }

            .qr-rating-icon {
                width: 50px;
                height: 50px;
            }

            .qr-rating-icon svg {
                width: 28px;
                height: 28px;
            }
        }

        @media (max-width: 340px) {
            .qr-rating-shell {
                padding-inline: 8px;
            }

            .qr-rating-logo {
                width: 108px;
                height: 39px;
                flex-basis: 108px;
            }

            .qr-rating-hero {
                padding-inline: 14px;
            }

            .qr-rating-hero h1 {
                font-size: 26px;
            }

            .qr-employee {
                margin-inline: 12px;
            }

            .qr-employee-avatar {
                width: 154px;
                height: 154px;
                margin-bottom: -35px;
                border-radius: 40px;
            }

            .qr-employee-copy {
                padding: 46px 11px 9px;
            }

            .qr-employee-name {
                font-size: 15px;
            }

            .qr-employee-meta {
                font-size: 12px;
            }

            .qr-rating-form {
                padding-inline: 12px;
            }

            .qr-rating-options {
                gap: 6px;
            }

            .qr-rating-option {
                min-height: 88px;
            }

            .qr-submit {
                min-height: 50px;
                font-size: 15px;
            }
        }
    </style>
</head>

<body class="qr-rating-page">
    <main class="qr-rating-shell" aria-live="polite" data-responsive-widths="320,375,390,430">
        <section class="qr-rating-card">
            <div class="qr-rating-top">
                <div class="qr-rating-logo">
                    <img src="{{ $qrLogoUrl }}" alt="MAXSIM">
                </div>
                <div class="qr-rating-pill">ĐÁNH GIÁ</div>
            </div>

            <header class="qr-rating-hero">
                <h1>ĐÁNH GIÁ DỊCH VỤ</h1>
            </header>

            @if ($state === 'allowed' || $state === 'form')
                <section class="qr-employee" aria-label="Nhân sự được đánh giá">
                    <div class="qr-employee-avatar">
                        @if (!empty($data['employee_avatar_url']))
                            <img
                                src="{{ $data['employee_avatar_url'] }}"
                                alt="{{ $data['employee_name'] ?? 'Nhan su MAXSIM' }}"
                                onerror="this.remove(); this.nextElementSibling.hidden=false;"
                            >
                        @endif

                        <span @if (!empty($data['employee_avatar_url'])) hidden @endif>
                            {{ $data['employee_initial'] ?? '?' }}
                        </span>
                    </div>

                    <div class="qr-employee-copy">
                        <div class="qr-employee-name">{{ $data['employee_name'] ?? 'Nhân sự MAXSIM' }}</div>
                        @if (!empty($data['employee_code']) && $data['employee_code'] !== '----')
                            <div class="qr-employee-meta">ID {{ $data['employee_code'] }}</div>
                        @endif
                        <div class="qr-employee-meta">
                            {{ !empty($data['branch_name']) ? ' ' . $data['branch_name'] : '' }}
                        </div>
                    </div>
                </section>

                <form class="qr-rating-form" method="POST" action="{{ $formAction }}" data-qr-rating-form data-thank-you-url="{{ $thankYouUrl }}" novalidate>
                    @csrf

                    <p class="qr-rating-question">BẠN THẤY SAO?</p>

                    <div class="qr-rating-options" role="radiogroup" aria-label="Mức đánh giá">
                        <label class="qr-rating-option bad" data-qr-rating-option>
                            <input type="radio" name="rating" value="bad" @checked(old('rating') === 'bad')>
                            <span class="qr-rating-icon" aria-hidden="true">
                                <svg viewBox="0 0 42 42" focusable="false">
                                    <path d="M14 15l4 4m0-4l-4 4M24 15l4 4m0-4l-4 4M13 29c2.3-3.8 13.7-3.8 16 0"/>
                                    <path d="M7 21a14 14 0 1 0 28 0 14 14 0 0 0-28 0Z"/>
                                </svg>
                            </span>
                        </label>

                        <label class="qr-rating-option average" data-qr-rating-option>
                            <input type="radio" name="rating" value="average" @checked(old('rating') === 'average')>
                            <span class="qr-rating-icon" aria-hidden="true">
                                <svg viewBox="0 0 42 42" focusable="false">
                                    <path d="M15 17h.01M27 17h.01M14 28h14"/>
                                    <path d="M7 21a14 14 0 1 0 28 0 14 14 0 0 0-28 0Z"/>
                                </svg>
                            </span>
                        </label>

                        <label class="qr-rating-option good" data-qr-rating-option>
                            <input type="radio" name="rating" value="good" @checked(old('rating') === 'good')>
                            <span class="qr-rating-icon" aria-hidden="true">
                                <svg viewBox="0 0 42 42" focusable="false">
                                    <path d="M15 17h.01M27 17h.01M14 25c3 4.8 9 4.8 12 0"/>
                                    <path d="M7 21a14 14 0 1 0 28 0 14 14 0 0 0-28 0Z"/>
                                    <path d="m31 8 .8 2.2L34 11l-2.2.8L31 14l-.8-2.2L28 11l2.2-.8L31 8Z"/>
                                </svg>
                            </span>
                        </label>
                    </div>

                    <div class="qr-message-field">
                        <div class="qr-message-head">
                            <label for="comment">LỜI NHẮN</label>
                            <small class="qr-char-count" data-qr-rating-counter>0/300</small>
                        </div>
                        <textarea
                            id="comment"
                            class="qr-textarea"
                            name="comment"
                            maxlength="300"
                            placeholder="VIẾT THÊM NẾU MUỐN..."
                            data-qr-rating-comment
                        >{{ old('comment') }}</textarea>
                    </div>

                    <button class="qr-submit" type="submit" data-qr-rating-submit>
                        <span data-qr-rating-submit-text>CHỌN 1 MỨC ĐÁNH GIÁ</span>
                    </button>
                    
                </form>
            @elseif ($state === 'blocked_internal_device' || $state === 'internal_blocked')
                <section class="qr-state">
                    <div class="qr-state-icon">!</div>
                    <h2>THIẾT BỊ NỘI BỘ</h2>
                    <p>Thiết bị nội bộ không thể gửi đánh giá.</p>
                </section>
            @else
                <section class="qr-state">
                    <div class="qr-state-icon">!</div>
                    <h2>{{ $data['title'] ?? 'Chưa thể đánh giá' }}</h2>
                    <p>{{ $data['message'] ?? 'Vui lòng thử lại sau.' }}</p>
                </section>
            @endif
        </section>
    </main>

    <div class="qr-modal-layer" data-qr-modal data-qr-success-modal data-open="{{ session('rating_success') ? 'true' : 'false' }}" data-thank-you-url="{{ $thankYouUrl }}" @unless (session('rating_success')) hidden @endunless role="dialog" aria-modal="true" aria-labelledby="qr-success-title">
        <section class="qr-modal">
            <div class="qr-modal-mark good">✓</div>
            <h2 id="qr-success-title">CẢM ƠN QUÝ KHÁCH!</h2>
            <p>Ý kiến của Quý khách đã được ghi nhận.</p>
            <button type="button" data-qr-modal-close>HOÀN TẤT</button>
        </section>
    </div>

    @php
        $modalError = $errorModal ?: ($errors->any() ? [
            'title' => 'CHƯA GỬI ĐƯỢC',
            'message' => $errors->first(),
        ] : (($state !== 'allowed' && $state !== 'form' && $state !== 'blocked_internal_device' && $state !== 'internal_blocked') ? [
            'title' => $data['title'] ?? 'Chưa thể đánh giá',
            'message' => $data['message'] ?? 'Vui lòng thử lại sau.',
        ] : null));
    @endphp

    <div class="qr-modal-layer" data-qr-modal data-qr-error-modal data-open="{{ $modalError ? 'true' : 'false' }}" @unless ($modalError) hidden @endunless role="dialog" aria-modal="true" aria-labelledby="qr-error-title">
        <section class="qr-modal">
            <div class="qr-modal-mark warn">!</div>
            <h2 id="qr-error-title" data-qr-error-title>{{ $modalError['title'] ?? 'CHƯA GỬI ĐƯỢC' }}</h2>
            <p data-qr-error-message>{{ $modalError['message'] ?? 'Vui lòng thử lại sau.' }}</p>
            <button type="button" data-qr-modal-close>Đóng</button>
        </section>
    </div>

    <div class="qr-modal-layer" data-qr-modal data-qr-private-modal hidden role="dialog" aria-modal="true" aria-labelledby="qr-private-title">
        <section class="qr-modal">
            <div class="qr-modal-mark warn">!</div>
            <h2 id="qr-private-title">MỞ BẰNG TAB THƯỜNG</h2>
            <p>Để bảo đảm đánh giá được ghi nhận đúng, vui lòng mở liên kết này bằng tab trình duyệt thông thường.</p>
            <button type="button" data-qr-modal-close data-qr-private-reload>TÔI ĐÃ MỞ TAB THƯỜNG</button>
        </section>
    </div>

    <div class="qr-modal-layer" data-qr-modal data-qr-internal-modal data-open="{{ ($state === 'blocked_internal_device' || $state === 'internal_blocked') ? 'true' : 'false' }}" @unless ($state === 'blocked_internal_device' || $state === 'internal_blocked') hidden @endunless role="dialog" aria-modal="true" aria-labelledby="qr-internal-title">
        <section class="qr-modal">
            <div class="qr-modal-mark warn">!</div>
            <h2 id="qr-internal-title">THIẾT BỊ NỘI BỘ</h2>
            <p>Thiết bị nội bộ không thể gửi đánh giá.</p>
            <button type="button" data-qr-modal-close>Đóng</button>
        </section>
    </div>
</body>

</html>
