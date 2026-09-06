<x-app-layout>
    @php
        $staffName = trim((string) ($card['name'] ?? ''));
        $staffNameLength = mb_strlen($staffName);

        $staffNameClass = match (true) {
            $staffNameLength > 30 => 'staff-card__name--very-long',
            $staffNameLength > 22 => 'staff-card__name--long',
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
            padding: .8mm 1mm;

            display: grid;
            grid-template-columns: 18.5mm minmax(0, 1fr) 19.5mm;
            align-items: center;
            column-gap: .7mm;

            border: .5mm solid var(--card-gold);
            border-radius: 2.5mm;
            box-shadow: inset 0 0 0 .18mm rgba(255, 224, 132, .42);
        }

        .staff-card--horizontal::after {
            content: "";
            position: absolute;
            inset: 1mm;
            border: .18mm solid rgba(244, 207, 103, .35);
            border-radius: 1.7mm;
            pointer-events: none;
        }

        .staff-card__horizontal-logo,
        .staff-card__horizontal-identity,
        .staff-card__horizontal-qr {
            position: relative;
            z-index: 2;
        }

        .staff-card__horizontal-logo {
            display: grid;
            place-items: center start;
            width: 18.5mm;
            height: 18mm;
            overflow: hidden;
        }

        .staff-card__horizontal-logo img {
            display: block;
            width: 18mm;
            height: 17mm;
            object-fit: contain;
        }

        .staff-card__horizontal-identity {
            min-width: 0;
            padding: 0 .45mm 0 .9mm;
            overflow: hidden;
            border-left: .22mm solid rgba(213, 167, 47, .72);
            text-align: center;
        }

        .staff-card__name {
            margin: 0;
            overflow: hidden;
            color: var(--card-text);
            font-size: 10.5px;
            line-height: 1.04;
            font-weight: 900;
            letter-spacing: -.12px;
            text-transform: uppercase;
            white-space: nowrap;
            word-break: normal;
            overflow-wrap: normal;
        }

        .staff-card__name--long {
            font-size: 9px;
            letter-spacing: -.28px;
        }

        .staff-card__name--very-long {
            max-height: 2.1em;
            font-size: 8px;
            line-height: 1.02;
            letter-spacing: -.32px;
            white-space: normal;
        }

        .staff-card__position {
            margin: .75mm 0 0;
            overflow: hidden;
            color: var(--card-muted);
            font-size: 8.5px;
            line-height: 1;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .staff-card__horizontal-code {
            display: inline-block;
            margin-top: .65mm;
            padding: .28mm 1.6mm;

            color: var(--card-gold-light);
            border: .22mm solid var(--card-gold);
            border-radius: .8mm;

            font-size: 7.5px;
            line-height: 1;
            font-weight: 900;
            letter-spacing: .18em;
        }

        .staff-card__horizontal-qr {
            justify-self: end;
            display: grid;
            place-items: center;

            width: 18.5mm;
            height: 18.5mm;
            padding: .55mm;

            overflow: hidden;
            color: #111;
            background: #fff;
            border: .28mm solid var(--card-gold);
            border-radius: 1.2mm;

            font-size: 6px;
            line-height: 1.1;
            font-weight: 700;
            text-align: center;
        }

        .staff-card__horizontal-qr img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            image-rendering: pixelated;
        }

        /* ==============================
         * Mẫu A5 148 × 210 mm
         * ============================== */
        .staff-card--vertical {
            width: 148mm;
            height: 210mm;
            border: 0;
        }

        .staff-card__vertical-frame {
            position: absolute;
            z-index: 20;
            top: 3mm;
            left: 3mm;
            width: 142mm;
            height: 204mm;
            border: .6mm solid var(--card-gold);
            border-radius: 3mm;
            box-shadow: inset 0 0 0 .2mm rgba(255, 226, 139, .38);
            pointer-events: none;
        }

        .staff-card__a5-header {
            position: absolute;
            z-index: 2;
            top: 9mm;
            right: 11mm;
            left: 11mm;
            height: 36mm;
        }

        .staff-card__maxsim-logo {
            position: absolute;
            top: 1mm;
            left: 0;
            width: 20mm;
            height: 10mm;
            object-fit: contain;
            opacity: .92;
            filter: brightness(0) invert(1);
        }

        .staff-card__branch-brand {
            position: absolute;
            top: 0;
            left: 50%;
            width: 74mm;
            transform: translateX(-50%);
            text-align: center;
        }

        .staff-card__branch-brand > img {
            display: block;
            width: 60mm;
            height: 27mm;
            margin: 0 auto;
            object-fit: contain;
        }

        .staff-card__brand-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3mm;
            margin-top: .8mm;
        }

        .staff-card__brand-divider span {
            width: 26mm;
            height: .3mm;
            background: linear-gradient(90deg, transparent, var(--card-gold), transparent);
        }

        .staff-card__brand-divider b {
            color: var(--card-gold-light);
            font-size: 12px;
            line-height: 1;
        }

        .staff-card__a5-info {
            position: absolute;
            z-index: 2;
            top: 51mm;
            right: 12mm;
            left: 12mm;
            height: 66mm;

            display: grid;
            grid-template-columns: 56mm minmax(0, 1fr);
            align-items: center;
            column-gap: 4mm;

            padding-top: 4mm;
            border-top: .25mm solid rgba(213, 167, 47, .62);
        }

        .staff-card__avatar {
            display: grid;
            place-items: center;
            width: 54mm;
            height: 58mm;
            overflow: hidden;
            color: #fff;
            background: linear-gradient(145deg, #2a2a2a, #101010);
            border: .55mm solid var(--card-gold);
            border-radius: 2.5mm;
            font-size: 42px;
            font-weight: 900;
        }

        .staff-card__avatar img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .staff-card__a5-identity {
            min-width: 0;
            padding-left: 0;
            overflow: hidden;
            text-align: left;
        }

        .staff-card__a5-identity .staff-card__name {
            font-size: 20px;
            line-height: 1.08;
            letter-spacing: -.3px;
            text-align: left;
            white-space: nowrap;
        }

        .staff-card__a5-identity .staff-card__name--long {
            font-size: 17px;
            letter-spacing: -.5px;
        }

        .staff-card__a5-identity .staff-card__name--very-long {
            max-height: 2.2em;
            font-size: 15px;
            line-height: 1.08;
            white-space: normal;
        }

        .staff-card__a5-identity .staff-card__position {
            margin-top: 3mm;
            font-size: 15px;
            line-height: 1.1;
            text-align: left;
        }

        .staff-card__a5-rating {
            position: absolute;
            z-index: 2;
            top: 126mm;
            right: 12mm;
            bottom: 12mm;
            left: 12mm;

            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;

            padding-top: 6mm;
            border-top: .25mm solid rgba(213, 167, 47, .48);
        }

        .staff-card__a5-qr {
            display: grid;
            place-items: center;
            width: 44mm;
            height: 44mm;
            padding: 1mm;
            overflow: hidden;
            color: #111;
            background: #fff;
            border: .45mm solid var(--card-gold);
            border-radius: 2mm;
            font-size: 10px;
            font-weight: 800;
            text-align: center;
        }

        .staff-card__a5-qr img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            image-rendering: pixelated;
        }

        .staff-card__a5-code {
            margin-top: 4mm;
            padding: 1.3mm 6.5mm;
            color: var(--card-gold-light);
            border: .35mm solid var(--card-gold);
            border-radius: 1.5mm;
            font-size: 19px;
            line-height: 1;
            font-weight: 900;
            letter-spacing: .22em;
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

                            <p class="staff-card__position">
                                {{ mb_strtoupper((string) ($card['position'] ?? 'Nhân viên'), 'UTF-8') }}
                            </p>

                            <span class="staff-card__horizontal-code">
                                {{ $card['citizen_last4'] ?? '----' }}
                            </span>
                        </div>

                        <div class="staff-card__horizontal-qr">
                            @if (! empty($card['qr_svg_url']))
                                <img src="{{ $card['qr_svg_url'] }}" alt="QR đánh giá">
                            @else
                                <span>Chưa có QR</span>
                            @endif
                        </div>
                    </article>
                </div>
            </section>

            <section class="space-y-3">
                <div>
                    <h2 class="text-lg font-black text-slate-900">Bảng đánh giá A5 · 148 × 210 mm</h2>
                    <p class="text-sm text-slate-500">Bảng treo trong phòng, ảnh bên trái, tên và vị trí bên phải.</p>
                </div>

                <div class="staff-card-preview__stage flex min-h-[850px] items-center justify-center rounded-2xl bg-slate-100 p-4">
                    <article class="staff-card staff-card--vertical">
                        <div class="staff-card__vertical-frame"></div>

                        <header class="staff-card__a5-header">
                            @if (! empty($card['maxsim_logo_url']))
                                <img
                                    class="staff-card__maxsim-logo"
                                    src="{{ $card['maxsim_logo_url'] }}"
                                    alt="MAXSIM"
                                >
                            @endif

                            <div class="staff-card__branch-brand">
                                @if (! empty($card['branch_logo_url']))
                                    <img src="{{ $card['branch_logo_url'] }}" alt="Logo cơ sở">
                                @endif

                                <div class="staff-card__brand-divider">
                                    <span></span>
                                    <b>★</b>
                                    <span></span>
                                </div>
                            </div>
                        </header>

                        <section class="staff-card__a5-info">
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

                            <div class="staff-card__a5-identity">
                                <h3 class="staff-card__name {{ $staffNameClass }}">
                                    {{ mb_strtoupper($staffName, 'UTF-8') }}
                                </h3>

                                <p class="staff-card__position">
                                    {{ mb_strtoupper((string) ($card['position'] ?? 'Nhân viên'), 'UTF-8') }}
                                </p>
                            </div>
                        </section>

                        <section class="staff-card__a5-rating">
                            <div class="staff-card__a5-qr">
                                @if (! empty($card['qr_svg_url']))
                                    <img src="{{ $card['qr_svg_url'] }}" alt="QR đánh giá">
                                @else
                                    <span>Chưa có QR đánh giá</span>
                                @endif
                            </div>

                            <div class="staff-card__a5-code">
                                {{ $card['citizen_last4'] ?? '----' }}
                            </div>
                        </section>
                    </article>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>