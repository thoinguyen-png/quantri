<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <style>
        @if ($template ==='vertical') @page {
            size: A5 portrait;
            margin: 0;
        }

        @else @page {
            size: A4 portrait;
            margin: 8mm 10mm;
        }

        @endif * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            color: #f8f8f8;
            background: #fff;
            font-family: "DejaVu Sans", sans-serif;
        }

        .pdf-page {
            margin: 0;
            overflow: hidden;
            page-break-after: always;
        }

        .pdf-page:last-child {
            page-break-after: auto;
        }

        .pdf-page--horizontal {
            width: 190mm;
            min-height: 281mm;
        }

        .pdf-page--vertical {
            width: 148mm;
            height: 210mm;
        }

        .sheet--horizontal {
            width: 100%;
            border-spacing: 8mm 2mm;
            border-collapse: separate;
            table-layout: fixed;
        }

        .slot--horizontal {
            width: 80mm;
            height: 24mm;
            margin: 0;
            padding: 0;
            vertical-align: top;
            text-align: center;
            page-break-inside: avoid;
        }

        .staff-card {
            position: relative;
            overflow: hidden;
            color: #f8f8f8;
            background: #060606;
            page-break-inside: avoid;
        }

        /* ==============================
         * Mẫu ngang 80 × 24 mm
         * ============================== */
        .staff-card--horizontal {
            width: 80mm;
            height: 24mm;
            margin: 0 auto;
            padding: 0;
            border: .45mm solid #cca236;
            border-radius: 2.5mm;
            background: #cca236;
            overflow: hidden;
        }

        .horizontal-layout {
            position: relative;
            width: 100%;
            height: 24mm;
            margin: 0;
            padding: 0;
        }

        .horizontal-logo {
            position: absolute;
            top: 0;
            left: 0;
            box-sizing: border-box;
            width: 19.2mm;
            height: 24mm;
            background: #000000;
            text-align: center;
        }

        .horizontal-logo-table {
            width: 100%;
            height: 24mm;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
        }

        .horizontal-logo-cell {
            height: 24mm;
            vertical-align: middle;
            text-align: center;
            padding: 0 1.2mm;
        }

        .horizontal-logo-cell img {
            display: inline-block;
            max-width: 16.2mm;
            max-height: 17.5mm;
            width: auto;
            height: auto;
            vertical-align: middle;
        }

        .horizontal-info {
            position: absolute;
            top: 0;
            right: 22.8mm;
            left: 19.2mm;
            box-sizing: border-box;
            width: 38mm;
            height: 24mm;
            min-width: 0;
            padding: 0;
            text-align: center;
            background: #cca236;
        }

        .horizontal-info-table {
            width: 100%;
            height: 24mm;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
        }

        .horizontal-info-cell {
            height: 24mm;
            vertical-align: middle;
            text-align: center;
            padding: 0 1mm;
        }

        .horizontal-name {
            margin: 0;
            color: #000000;
            font-size: 7.6pt;
            line-height: 1.04;
            font-weight: 700;
            letter-spacing: -.08pt;
            text-transform: uppercase;
            white-space: nowrap;
            word-wrap: normal;
        }

        .horizontal-name.name-long {
            font-size: 6.8pt;
            letter-spacing: -.18pt;
        }

        .horizontal-name.name-very-long {
            font-size: 6.1pt;
            line-height: 1.02;
            letter-spacing: -.22pt;
            white-space: normal;
            word-wrap: break-word;
        }

        .horizontal-divider-table {
            width: 27mm;
            margin: 1.0mm auto 0.8mm;
            border-collapse: collapse;
        }

        .horizontal-divider-dot {
            width: 1.8mm;
            color: #000000;
            font-size: 3.5pt;
            line-height: 1;
            text-align: center;
            vertical-align: middle;
            padding: 0;
        }

        .horizontal-divider-line-cell {
            vertical-align: middle;
            padding: 0 .4mm;
        }

        .horizontal-divider-line {
            height: 0;
            border-top: .28mm solid #000000;
            font-size: 0;
            line-height: 0;
        }

        .horizontal-position {
            margin: 0;
            color: #000000;
            font-size: 6.3pt;
            line-height: 1.15;
            font-weight: 700;
            white-space: nowrap;
        }

        .horizontal-code {
            margin-top: .6mm;
            color: #000000;
            font-size: 5.9pt;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 1.8pt;
        }

        .horizontal-qr-cell {
            position: absolute;
            top: 0;
            right: 1.2mm;
            box-sizing: border-box;
            width: 21.6mm;
            height: 24mm;
            text-align: right;
        }

        .horizontal-qr-table {
            width: 100%;
            height: 24mm;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
        }

        .horizontal-qr-cell-inner {
            height: 24mm;
            vertical-align: middle;
            text-align: center;
            padding: 0;
        }

        .horizontal-qr-box {
            box-sizing: border-box;
            display: inline-block;
            width: 21.6mm;
            height: 21.6mm;
            margin: 0;
            padding: .3mm;
            overflow: hidden;
            color: #111;
            background: #fff;
            border-radius: 1mm;
            vertical-align: middle;
        }

        .horizontal-qr-box img {
            display: block;
            width: 100%;
            height: 100%;
        }

        /* ==============================
         * Mẫu A5 148 × 210 mm (Bảng treo phòng)
         * ============================== */
        .staff-card--vertical {
            position: relative;
            width: 148mm;
            height: 210mm;
            border: 0;
            border-radius: 0;
            background: #0c0d12;
            color: #f8f8f8;
            overflow: hidden;
        }

        .vertical-frame {
            position: absolute;
            z-index: 20;
            top: 3.5mm;
            left: 3.5mm;
            width: 141mm;
            height: 203mm;
            border: .7mm solid #c99b3b;
            border-radius: 4mm;
        }

        .vertical-inner-frame {
            position: absolute;
            z-index: 20;
            top: 4.8mm;
            left: 4.8mm;
            width: 138.4mm;
            height: 200.4mm;
            border: .22mm solid rgba(201, 155, 59, 0.45);
            border-radius: 3mm;
        }

        .vertical-circuit {
            position: absolute;
            z-index: 1;
            top: 20mm;
            width: 32mm;
            height: 165mm;
        }

        .vertical-circuit--left {
            left: 5mm;
        }

        .vertical-circuit--right {
            right: 5mm;
        }

        .vertical-header {
            position: absolute;
            z-index: 5;
            top: 6mm;
            left: 8mm;
            width: 132mm;
            height: 20mm;
            text-align: center;
        }

        .vertical-maxsim-slot {
            position: absolute;
            top: 0;
            left: 2mm;
            width: 22mm;
            text-align: center;
        }

        .vertical-maxsim {
            display: block;
            width: 17mm;
            height: auto;
            max-height: 12mm;
            margin: 0 auto;
        }

        .vertical-brand {
            position: absolute;
            top: 0;
            left: 50%;
            width: 80mm;
            margin-left: -40mm;
            text-align: center;
        }

        .vertical-brand-logo {
            display: block;
            width: auto;
            height: auto;
            max-width: 62mm;
            max-height: 18mm;
            margin: 0 auto;
        }

        .vertical-profile {
            position: absolute;
            z-index: 5;
            top: 31mm;
            left: 50%;
            width: 64mm;
            margin-left: -32mm;
            text-align: center;
        }

        .vertical-avatar {
            box-sizing: border-box;
            display: block;
            width: 58mm;
            height: 60mm;
            margin: 0 auto;
            overflow: hidden;
            color: #fff;
            background: #181920;
            border: .7mm solid #c99b3b;
            border-radius: 4mm;
            font-size: 30pt;
            font-weight: 700;
            line-height: 1;
            text-align: center;
        }

        .vertical-avatar img {
            display: block;
            width: 100%;
            height: 100%;
        }

        .vertical-identity {
            position: absolute;
            z-index: 5;
            top: 94mm;
            left: 8mm;
            width: 132mm;
            margin: 0 auto;
            padding: 0;
            text-align: center;
        }

        .vertical-name {
            margin: 0;
            color: #ffffff;
            font-size: 13pt;
            line-height: 1.1;
            font-weight: 700;
            letter-spacing: .4pt;
            text-transform: uppercase;
            white-space: normal;
            word-wrap: break-word;
            text-align: center;
        }

        .vertical-name.name-long {
            font-size: 10.8pt;
            letter-spacing: -.1pt;
        }

        .vertical-name.name-very-long {
            font-size: 9pt;
            line-height: 1.05;
        }

        .vertical-position {
            margin-top: 1mm;
            color: #d4a53b;
            font-size: 9pt;
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: .8pt;
            text-transform: uppercase;
            text-align: center;
        }

        .vertical-meta-table {
            margin: 1.2mm auto 0;
            border-collapse: collapse;
        }

        .vertical-meta-icon-cell {
            width: 4.5mm;
            vertical-align: top;
            text-align: center;
            padding: 0.3mm 0 0 0;
        }

        .vertical-meta-icon {
            display: block;
            width: 3.2mm;
            height: 3.2mm;
            margin: 0 auto;
        }

        .vertical-meta-text-cell {
            color: #e5e7eb;
            font-size: 8pt;
            font-weight: 600;
            line-height: 1.1;
            vertical-align: top;
            text-align: left;
            padding: 0 0 0.8mm 2mm;
            white-space: nowrap;
        }

        .vertical-rating-section {
            position: absolute;
            z-index: 5;
            top: 122mm;
            left: 8mm;
            width: 132mm;
            text-align: center;
        }

        .vertical-rating-table {
            margin: 0 auto;
            border-collapse: collapse;
        }

        .vertical-scanner-cell {
            width: 21mm;
            vertical-align: middle;
            text-align: right;
            padding-right: 4mm;
        }

        .vertical-scanner-icon {
            display: inline-block;
            width: 17mm;
            height: 22.1mm;
            vertical-align: middle;
        }

        .vertical-qr-cell {
            width: 33mm;
            vertical-align: middle;
            text-align: left;
        }

        .vertical-qr-box {
            box-sizing: border-box;
            display: inline-block;
            width: 32mm;
            height: 32mm;
            margin: 0;
            padding: .6mm;
            overflow: hidden;
            color: #111;
            background: #ffffff;
            border-radius: 3.5mm;
            text-align: center;
            vertical-align: middle;
        }

        .vertical-qr-box img {
            display: block;
            width: 100%;
            height: 100%;
        }

        .vertical-no-qr {
            color: #999;
            font-size: 7.5pt;
            line-height: 30mm;
        }

        .vertical-code {
            display: inline-block;
            margin: 1.5mm auto 0;
            padding: .7mm 5.5mm;
            color: #f2ce70;
            border: .35mm solid #c99b3b;
            border-radius: 1.5mm;
            font-size: 11pt;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 2.2pt;
            text-align: center;
        }

        .vertical-rating-hint {
            margin: 1.5mm auto 0;
            color: #c99b3b;
            font-size: 7.8pt;
            font-style: italic;
            line-height: 1.2;
            text-align: center;
        }

        .vertical-bottom-divider {
            width: 78mm;
            margin: 2.0mm auto 0;
            text-align: center;
        }

        .vertical-bottom-divider-img {
            display: block;
            width: 78mm;
            height: 3.9mm;
            margin: 0 auto;
        }

        .vertical-footer {
            position: absolute;
            z-index: 5;
            top: 191mm;
            left: 8mm;
            width: 132mm;
            text-align: center;
        }

        .vertical-slogan {
            color: #c99b3b;
            font-size: 8pt;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 1.4pt;
            text-align: center;
            text-transform: uppercase;
        }

        .vertical-sparkles-cluster {
            position: absolute;
            right: 10mm;
            top: -8mm;
            width: 11mm;
            height: 11mm;
            opacity: 0.85;
        }

        .vertical-sparkle-dot {
            position: absolute;
            right: 4mm;
            top: 1.5mm;
            color: #c99b3b;
            font-size: 7.5pt;
            line-height: 1;
            opacity: 0.65;
        }
    </style>
</head>

<body>
    @foreach ($pages as $page)
    <section class="pdf-page pdf-page--{{ $template }}">
        @if ($template === 'vertical')
        @include('staff_cards.partials.pdf-card', [
        'card' => $page->first(),
        'template' => 'vertical',
        ])
        @else
        <table class="sheet--horizontal">
            @foreach ($page->chunk(2) as $row)
            <tr>
                @foreach ($row as $card)
                <td class="slot--horizontal">
                    @include('staff_cards.partials.pdf-card', [
                    'card' => $card,
                    'template' => 'horizontal',
                    ])
                </td>
                @endforeach

                @if ($row->count() < 2)
                    <td class="slot--horizontal">
                    </td>
                    @endif
            </tr>
            @endforeach
        </table>
        @endif
    </section>
    @endforeach
</body>

</html>