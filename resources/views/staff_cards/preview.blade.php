<x-app-layout>
    @php
        $staffName = trim((string) ($card['name'] ?? ''));
        $staffPosition = trim((string) ($card['position'] ?? ''));
        $staffNameLength = mb_strlen($staffName);

        $staffNameClass = match (true) {
            $staffNameLength > 34 => 'staff-card__name--very-long',
            $staffNameLength > 24 => 'staff-card__name--long',
            default => '',
        };
    @endphp

    <style>
        .staff-card-preview {
            --card-bg: #050505;
            --card-bg-soft: #111111;
            --card-gold: #d5a72f;
            --card-gold-light: #f4cf67;
            --card-text: #ffffff;
            --card-muted: #e1bd58;

            font-family: Arial, Helvetica, sans-serif;
        }

        .staff-card-preview__grid {
            display: grid;
            gap: 2rem;
        }

        .staff-card-preview__stage {
            overflow-x: auto;
            overflow-y: hidden;
        }

        .staff-card {
            position: relative;
            flex: 0 0 auto;
            box-sizing: border-box;
            overflow: hidden;
            color: var(--card-text);
            background:
                radial-gradient(circle at 50% 0%, rgba(213, 167, 47, .12), transparent 27%),
                linear-gradient(145deg, var(--card-bg-soft), var(--card-bg) 65%);
        }

        /* ==============================
         * Mẫu ngang 80 × 24 mm
         * ============================== */
        .staff-card--horizontal {
            width: 80mm;
            height: 24mm;
            padding: 0;
            margin: 0 auto;

            display: grid;
            grid-template-columns: 19.2mm minmax(0, 1fr) 22.8mm;
            align-items: center;

            background: #cca236;
            border: .45mm solid #cca236;
            border-radius: 2.5mm;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .staff-card__horizontal-logo {
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 19.2mm;
            height: 24mm;
            background: #000000;
            padding: 1.5mm;
        }

        .staff-card__horizontal-logo img {
            display: block;
            width: auto;
            height: auto;
            max-width: 16mm;
            max-height: 17mm;
        }

        .staff-card__horizontal-identity {
            min-width: 0;
            height: 24mm;
            padding: 0 1mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: #cca236;
        }

        .staff-card__name {
            margin: 0;
            color: #000000;
            font-size: 7.5pt;
            line-height: 1.08;
            font-weight: 900;
            letter-spacing: -.08pt;
            text-transform: uppercase;
            white-space: nowrap;
            word-break: normal;
            overflow-wrap: normal;
        }

        .staff-card__name--long {
            font-size: 6.6pt;
            letter-spacing: -.16pt;
        }

        .staff-card__name--very-long {
            font-size: 5.6pt;
            line-height: 1.02;
            letter-spacing: -.22pt;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .staff-card__horizontal-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 27mm;
            gap: 1mm;
            margin: 1.0mm 0 0.8mm;
        }

        .staff-card__horizontal-divider-dot {
            color: #000000;
            font-size: 3.5pt;
            line-height: 1;
        }

        .staff-card__horizontal-divider-line {
            flex: 1;
            height: 0;
            border-top: .28mm solid #000000;
        }

        .staff-card__position {
            margin: 0;
            color: #000000;
            font-size: 6.3pt;
            line-height: 1.15;
            font-weight: 700;
            white-space: nowrap;
        }

        .staff-card__horizontal-code {
            display: block;
            margin-top: .6mm;
            color: #000000;
            font-size: 5.9pt;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 1.8pt;
        }

        .staff-card__horizontal-qr {
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 22.8mm;
            height: 24mm;
            padding-right: 1.2mm;
        }

        .staff-card__horizontal-qr-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 21.6mm;
            height: 21.6mm;
            padding: .3mm;
            background: #ffffff;
            border-radius: 1mm;
            overflow: hidden;
        }

        .staff-card__horizontal-qr-box img {
            display: block;
            width: 100%;
            height: 100%;
            image-rendering: pixelated;
        }

        /* ==============================
         * Mẫu A5 148 × 210 mm (Bảng treo phòng)
         * ============================== */
        .staff-card--vertical {
            width: 148mm;
            height: 210mm;
            border: 0;
            background: #0c0d12;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border-radius: 4mm;
        }

        .staff-card__vertical-frame {
            position: absolute;
            z-index: 20;
            top: 3.5mm;
            left: 3.5mm;
            width: 141mm;
            height: 203mm;
            border: .7mm solid var(--card-gold);
            border-radius: 4mm;
            box-shadow: inset 0 0 0 .22mm rgba(255, 226, 139, .35);
            pointer-events: none;
        }

        .staff-card__vertical-inner-frame {
            position: absolute;
            z-index: 20;
            top: 4.8mm;
            left: 4.8mm;
            width: 138.4mm;
            height: 200.4mm;
            border: .22mm solid rgba(201, 155, 59, 0.45);
            border-radius: 3mm;
            pointer-events: none;
        }

        .staff-card__circuit {
            position: absolute;
            z-index: 1;
            top: 22mm;
            width: 32mm;
            height: 162mm;
            pointer-events: none;
        }

        .staff-card__circuit--left {
            left: 5mm;
        }

        .staff-card__circuit--right {
            right: 5mm;
        }

        .staff-card__a5-header {
            position: absolute;
            z-index: 5;
            top: 6mm;
            right: 8mm;
            left: 8mm;
            height: 20mm;
            text-align: center;
        }

        .staff-card__maxsim-slot {
            position: absolute;
            top: 0;
            left: 2mm;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 22mm;
            background: transparent;
        }

        .staff-card__maxsim-logo {
            display: block;
            width: auto;
            height: auto;
            max-width: 17mm;
            max-height: 12mm;
            filter: brightness(0) invert(1);
        }

        .staff-card__branch-brand {
            position: absolute;
            top: 0;
            left: 50%;
            width: 80mm;
            transform: translateX(-50%);
            text-align: center;
        }

        .staff-card__branch-brand > img {
            display: block;
            width: auto;
            height: auto;
            max-width: 62mm;
            max-height: 18mm;
            margin: 0 auto;
        }

        .staff-card__a5-profile {
            position: absolute;
            z-index: 5;
            top: 31mm;
            left: 50%;
            width: 64mm;
            transform: translateX(-50%);
            text-align: center;
        }

        .staff-card__avatar {
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 58mm;
            height: 60mm;
            margin: 0 auto;
            overflow: hidden;
            color: #fff;
            background: #181920;
            border: .7mm solid var(--card-gold);
            border-radius: 4mm;
            font-size: 30pt;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }

        .staff-card__avatar img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .staff-card__a5-identity {
            position: absolute;
            z-index: 5;
            top: 94mm;
            left: 8mm;
            right: 8mm;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .staff-card__a5-identity .staff-card__name {
            color: #ffffff;
            font-size: 17px;
            line-height: 1.1;
            font-weight: 700;
            letter-spacing: .5px;
            text-align: center;
            text-transform: uppercase;
            white-space: normal;
        }

        .staff-card__a5-identity .staff-card__name--long {
            font-size: 14.5px;
            letter-spacing: 0;
        }

        .staff-card__a5-identity .staff-card__name--very-long {
            font-size: 12px;
            line-height: 1.05;
        }

        .staff-card__a5-identity .staff-card__position {
            margin-top: 1mm;
            color: var(--card-gold);
            font-size: 12.5px;
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: .8px;
            text-transform: uppercase;
            text-align: center;
        }

        .staff-card__a5-meta {
            margin-top: 1.2mm;
            display: inline-flex;
            flex-direction: column;
            gap: 1mm;
            align-items: flex-start;
        }

        .staff-card__a5-meta-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .staff-card__a5-meta-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            margin-top: 1.5px;
        }

        .staff-card__a5-meta-icon svg {
            display: block;
            width: 11.5px;
            height: 11.5px;
            stroke: var(--card-gold);
        }

        .staff-card__a5-meta-text {
            color: #e5e7eb;
            font-size: 10.5px;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
        }

        .staff-card__a5-rating-section {
            position: absolute;
            z-index: 5;
            top: 122mm;
            left: 8mm;
            right: 8mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .staff-card__a5-qr-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4.5mm;
        }

        .staff-card__a5-scanner-icon {
            display: block;
            width: 17mm;
            height: 22.1mm;
            flex-shrink: 0;
        }

        .staff-card__a5-qr {
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32mm;
            height: 32mm;
            padding: .6mm;
            overflow: hidden;
            color: #111;
            background: #fff;
            border-radius: 3.5mm;
            text-align: center;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
            flex-shrink: 0;
        }

        .staff-card__a5-qr img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            image-rendering: pixelated;
        }

        .staff-card__a5-code {
            display: inline-block;
            margin-top: 1.5mm;
            padding: .7mm 5.5mm;
            color: var(--card-gold-light);
            border: .35mm solid var(--card-gold);
            border-radius: 1.5mm;
            font-size: 15px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: .2em;
            text-align: center;
        }

        .staff-card__a5-rating-hint {
            margin-top: 1.5mm;
            color: var(--card-gold);
            font-size: 10.5px;
            font-style: italic;
            line-height: 1.25;
            text-align: center;
        }

        .staff-card__a5-bottom-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 78mm;
            margin: 2.2mm auto 0;
            gap: 6px;
        }

        .staff-card__a5-bottom-divider-line {
            flex: 1;
            height: 1.8px;
            background: linear-gradient(90deg, rgba(201, 155, 59, 0) 0%, rgba(244, 207, 103, 0.7) 35%, #f4cf67 100%);
            border-radius: 1px;
        }

        .staff-card__a5-bottom-divider-line--right {
            background: linear-gradient(90deg, #f4cf67 0%, rgba(244, 207, 103, 0.7) 65%, rgba(201, 155, 59, 0) 0%);
        }

        .staff-card__a5-bottom-divider-star {
            color: #f4cf67;
            font-size: 13.5px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-shadow: 0 0 8px rgba(244, 207, 103, 0.95), 0 0 14px rgba(244, 207, 103, 0.5);
        }

        .staff-card__a5-footer {
            position: absolute;
            z-index: 5;
            top: 191mm;
            left: 8mm;
            right: 8mm;
            text-align: center;
        }

        .staff-card__a5-slogan {
            color: var(--card-gold);
            font-size: 10.5px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .staff-card__a5-sparkles-cluster {
            position: absolute;
            right: 10mm;
            top: -8mm;
            width: 11mm;
            height: 11mm;
            pointer-events: none;
            opacity: 0.85;
        }

        .staff-card__a5-sparkle-dot {
            position: absolute;
            right: 4mm;
            top: 1.5mm;
            color: var(--card-gold);
            font-size: 10px;
            line-height: 1;
            opacity: 0.65;
        }

        @media (min-width: 1536px) {
            .staff-card-preview__grid {
                grid-template-columns: 360px minmax(640px, 1fr);
            }
        }

        @media (max-width: 900px) {
            .staff-card-preview__stage {
                justify-content: flex-start !important;
            }
        }
    </style>

    <div class="staff-card-preview mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold text-slate-500">Xem trước thẻ nhân sự</p>
                <h1 class="text-2xl font-black text-slate-950">{{ $staffName }}</h1>
            </div>

            <a
                href="{{ route('users.index') }}"
                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white"
            >
                Quay lại danh sách
            </a>
        </div>

        <div class="staff-card-preview__grid">
            <section class="space-y-3">
                <div>
                    <h2 class="text-lg font-black text-slate-900">Mẫu ngang · 80 × 24 mm</h2>
                    <p class="text-sm text-slate-500">Logo trái, thông tin giữa, QR bên phải.</p>
                </div>

                <div class="staff-card-preview__stage flex min-h-[170px] items-center justify-center rounded-2xl bg-slate-100 p-4">
                    <article class="staff-card staff-card--horizontal">
                        <div class="staff-card__horizontal-logo">
                            @if (! empty($card['branch_logo_url']))
                                <img src="{{ $card['branch_logo_url'] }}" alt="Logo cơ sở">
                            @endif
                        </div>

                        <div class="staff-card__horizontal-identity">
                            <h3 class="staff-card__name {{ $staffNameClass }}">
                                {{ mb_strtoupper($staffName, 'UTF-8') }}
                            </h3>

                            <div class="staff-card__horizontal-divider">
                                <span class="staff-card__horizontal-divider-dot">&#9670;</span>
                                <span class="staff-card__horizontal-divider-line"></span>
                                <span class="staff-card__horizontal-divider-dot">&#9670;</span>
                            </div>

                            @if ($staffPosition !== '')
                                <p class="staff-card__position">
                                    {{ $staffPosition }}
                                </p>
                            @endif

                            <span class="staff-card__horizontal-code">
                                {{ $card['citizen_last4'] ?? '----' }}
                            </span>
                        </div>

                        <div class="staff-card__horizontal-qr">
                            <div class="staff-card__horizontal-qr-box">
                                @if (! empty($card['qr_svg_url']))
                                    <img src="{{ $card['qr_svg_url'] }}" alt="QR đánh giá">
                                @else
                                    <span class="text-xs text-slate-400">Chưa có QR</span>
                                @endif
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="space-y-3">
                <div>
                    <h2 class="text-lg font-black text-slate-900">Bảng đánh giá A5 · 148 × 210 mm</h2>
                    <p class="text-sm text-slate-500">Bảng treo trong phòng, ảnh chân dung giữa, QR đánh giá bên dưới.</p>
                </div>

                <div class="staff-card-preview__stage flex min-h-[850px] items-center justify-center rounded-2xl bg-slate-100 p-4">
                    <article class="staff-card staff-card--vertical">
                        <div class="staff-card__vertical-frame"></div>
                        <div class="staff-card__vertical-inner-frame"></div>

                        <header class="staff-card__a5-header">
                            <div class="staff-card__maxsim-slot">
                                @if (! empty($card['maxsim_logo_url']))
                                    <img
                                        class="staff-card__maxsim-logo"
                                        src="{{ $card['maxsim_logo_url'] }}"
                                        alt="MAXSIM"
                                    >
                                @endif
                            </div>

                            <div class="staff-card__branch-brand">
                                @if (! empty($card['branch_logo_url']))
                                    <img src="{{ $card['branch_logo_url'] }}" alt="Logo cơ sở">
                                @endif
                            </div>
                        </header>

                        <section class="staff-card__a5-profile">
                            <div class="staff-card__avatar">
                                @if (! empty($card['avatar_url']))
                                    <img
                                        src="{{ $card['avatar_url'] }}"
                                        alt="Ảnh đại diện {{ $staffName }}"
                                    >
                                @else
                                    {{ mb_strtoupper(mb_substr($staffName, 0, 1), 'UTF-8') }}
                                @endif
                            </div>
                        </section>

                        <section class="staff-card__a5-identity">
                            <h3 class="staff-card__name {{ $staffNameClass }}">
                                {{ mb_strtoupper($staffName, 'UTF-8') }}
                            </h3>

                            @if ($staffPosition !== '')
                                <p class="staff-card__position">
                                    {{ mb_strtoupper($staffPosition, 'UTF-8') }}
                                </p>
                            @endif

                            <div class="staff-card__a5-meta">
                                <div class="staff-card__a5-meta-row">
                                    <span class="staff-card__a5-meta-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </span>
                                    <span class="staff-card__a5-meta-text">{{ $card['work_hours'] ?? '11:00 - 05:00' }}</span>
                                </div>
                                <div class="staff-card__a5-meta-row">
                                    <span class="staff-card__a5-meta-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
                                    </span>
                                    <span class="staff-card__a5-meta-text">{{ $card['hotline'] ?? $card['phone_masked'] ?? '09xx-xxx-xxx' }}</span>
                                </div>
                            </div>
                        </section>

                        <section class="staff-card__a5-rating-section">
                            <div class="staff-card__a5-qr-row">
                                <svg class="staff-card__a5-scanner-icon" viewBox="0 0 100 130" fill="none" stroke="#d4a53b" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="14" y="6" width="60" height="114" rx="10" stroke="#d4a53b" stroke-width="3.2" />
                                    <line x1="36" y1="16" x2="52" y2="16" stroke-width="3" />
                                    <circle cx="44" cy="107" r="3.2" stroke-width="2.5" />
                                    <path d="M26 42 L26 32 L36 32" stroke-width="2.8" />
                                    <path d="M62 42 L62 32 L52 32" stroke-width="2.8" />
                                    <path d="M26 66 L26 76 L36 76" stroke-width="2.8" />
                                    <path d="M62 66 L62 76 L52 76" stroke-width="2.8" />
                                    <circle cx="44" cy="54" r="11" stroke-width="2.5" />
                                    <circle cx="44" cy="54" r="5" stroke-width="2" />
                                    <circle cx="68" cy="94" r="13" fill="#0c0d12" stroke="#d4a53b" stroke-width="2.6" />
                                    <polygon points="68,85.5 70.6,90.8 76.5,91.7 72.2,95.9 73.2,101.8 68,99 62.8,101.8 63.8,95.9 59.5,91.7 65.4,90.8" fill="none" stroke="#d4a53b" stroke-width="1.6" stroke-linejoin="round" />
                                </svg>

                                <div class="staff-card__a5-qr">
                                    @if (! empty($card['qr_svg_url']))
                                        <img src="{{ $card['qr_svg_url'] }}" alt="QR đánh giá">
                                    @else
                                        <span class="text-xs text-slate-400">Chưa có QR</span>
                                    @endif
                                </div>
                            </div>

                            <div class="staff-card__a5-code">
                                {{ $card['citizen_last4'] ?? '----' }}
                            </div>

                            <div class="staff-card__a5-rating-hint">
                                Hãy quét mã để gửi lời cảm ơn hoặc đóng góp ý kiến.
                            </div>

                            <div class="staff-card__a5-bottom-divider">
                                <div class="staff-card__a5-bottom-divider-line staff-card__a5-bottom-divider-line--left"></div>
                                <span class="staff-card__a5-bottom-divider-star">★</span>
                                <div class="staff-card__a5-bottom-divider-line staff-card__a5-bottom-divider-line--right"></div>
                            </div>
                        </section>

                        <footer class="staff-card__a5-footer">
                            <div class="staff-card__a5-slogan">
                                {{ $card['slogan'] ?? 'HÀI LÒNG - QUAN TÂM - TRÂN TRỌNG' }}
                            </div>
                            <svg class="staff-card__a5-sparkles-cluster" viewBox="0 0 80 80" fill="none">
                                <path d="M36 4 C36 24 20 36 4 36 C20 36 36 48 36 68 C36 48 52 36 68 36 C52 36 36 24 36 4 Z" fill="#f4cf67" opacity="0.85"/>
                                <path d="M58 34 C58 44 48 52 38 52 C48 52 58 60 58 70 C58 60 68 52 78 52 C68 52 58 44 58 34 Z" fill="#ffffff" opacity="0.9"/>
                            </svg>
                            <div class="staff-card__a5-sparkle-dot">&#10022;</div>
                        </footer>
                    </article>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
