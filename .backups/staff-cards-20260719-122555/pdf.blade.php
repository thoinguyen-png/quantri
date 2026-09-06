<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">

    <style>
        @if ($template === 'vertical')
            @page {
                size: A5 portrait;
                margin: 0;
            }
        @else
            @page {
                size: A4 portrait;
                margin: 8mm 10mm;
            }
        @endif

        * {
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
            padding: .65mm .8mm;
            border: .5mm solid #d4a53b;
            border-radius: 2.5mm;
        }

        .horizontal-layout {
            width: 100%;
            height: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .horizontal-layout td {
            padding: 0;
            vertical-align: middle;
        }

        .horizontal-logo {
            width: 18.5mm;
            height: 18mm;
            padding-right: .5mm !important;
            overflow: hidden;
            text-align: left;
        }

        .horizontal-logo img {
            display: inline-block;
            width: 18mm;
            max-width: 18mm;
            height: 17mm;
            max-height: 17mm;
        }

        .horizontal-info {
            padding: 0 .4mm 0 .8mm !important;
            overflow: hidden;
            border-left: .22mm solid rgba(212, 165, 59, .75);
            text-align: center;
        }

        .horizontal-name {
            margin: 0;
            overflow: hidden;
            color: #f8f8f8;
            font-size: 7.7pt;
            line-height: 1.03;
            font-weight: 700;
            letter-spacing: -.12pt;
            text-transform: uppercase;
            white-space: nowrap;
            word-wrap: normal;
        }

        .horizontal-name.name-long {
            font-size: 6.7pt;
            letter-spacing: -.22pt;
        }

        .horizontal-name.name-very-long {
            max-height: 2.08em;
            font-size: 5.9pt;
            line-height: 1.02;
            white-space: normal;
        }

        .horizontal-position {
            margin-top: .7mm;
            overflow: hidden;
            color: #f2ce70;
            font-size: 6pt;
            line-height: 1;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .horizontal-code {
            display: inline-block;
            margin-top: .62mm;
            padding: .25mm 1.5mm;
            color: #f2ce70;
            border: .2mm solid #d4a53b;
            border-radius: .75mm;
            font-size: 6pt;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 1pt;
        }

        .horizontal-qr-cell {
            width: 19.5mm;
            height: 19.5mm;
            padding-left: .4mm !important;
            text-align: right;
        }

        .horizontal-qr-box {
            display: inline-block;
            width: 18.5mm;
            height: 18.5mm;
            padding: .5mm;
            overflow: hidden;
            color: #111;
            background: #fff;
            border: .25mm solid #d4a53b;
            border-radius: 1mm;
            font-size: 5pt;
            line-height: 17mm;
            text-align: center;
        }

        .horizontal-qr-box img {
            display: block;
            width: 100%;
            height: 100%;
        }

        /* ==============================
         * Mẫu A5 148 × 210 mm
         * ============================== */
        .staff-card--vertical {
            position: relative;
            width: 148mm;
            height: 210mm;
            border: 0;
            border-radius: 0;
        }

        .vertical-frame {
            position: absolute;
            z-index: 20;
            top: 3mm;
            left: 3mm;
            width: 142mm;
            height: 204mm;
            border: .55mm solid #d4a53b;
        }

        .vertical-header {
            position: absolute;
            z-index: 2;
            top: 9mm;
            left: 11mm;
            width: 126mm;
            height: 36mm;
        }

        .vertical-maxsim {
            position: absolute;
            top: 1mm;
            left: 0;
            width: 20mm;
            height: 10mm;
        }

        .vertical-brand {
            position: absolute;
            top: 0;
            left: 50%;
            width: 74mm;
            margin-left: -37mm;
            text-align: center;
        }

        .vertical-brand-logo {
            display: block;
            width: 60mm;
            height: 27mm;
            margin: 0 auto;
        }

        .vertical-divider {
            width: 60mm;
            margin: .8mm auto 0;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .vertical-divider td {
            padding: 0;
            vertical-align: middle;
        }

        .vertical-divider-line {
            width: 25mm;
            border-top: .3mm solid #d4a53b;
        }

        .vertical-divider-star {
            width: 8mm;
            color: #f2ce70;
            font-size: 10pt;
            line-height: 1;
            text-align: center;
        }

        .vertical-info {
            position: absolute;
            z-index: 2;
            top: 51mm;
            left: 12mm;
            width: 124mm;
            height: 66mm;
            padding-top: 4mm;
            border-top: .25mm solid #8d702e;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .vertical-info td {
            padding-top: 4mm;
            vertical-align: middle;
        }

        .vertical-avatar-cell {
            width: 58mm;
        }

        .vertical-avatar {
            width: 54mm;
            height: 58mm;
            overflow: hidden;
            color: #fff;
            background: #222;
            border: .55mm solid #d4a53b;
            border-radius: 2.5mm;
            font-size: 30pt;
            font-weight: 700;
            line-height: 58mm;
            text-align: center;
        }

        .vertical-avatar img {
            display: block;
            width: 54mm;
            height: 58mm;
            object-fit: cover;
        }

        .vertical-identity {
            padding-left: 3mm !important;
            overflow: hidden;
            text-align: left;
        }

        .vertical-name {
            margin: 0;
            overflow: hidden;
            color: #f8f8f8;
            font-size: 13pt;
            line-height: 1.07;
            font-weight: 700;
            letter-spacing: -.22pt;
            text-transform: uppercase;
            white-space: nowrap;
            word-wrap: normal;
        }

        .vertical-name.name-long {
            font-size: 11.3pt;
            letter-spacing: -.35pt;
        }

        .vertical-name.name-very-long {
            max-height: 2.2em;
            font-size: 9.8pt;
            line-height: 1.06;
            white-space: normal;
        }

        .vertical-position {
            margin-top: 3mm;
            color: #f2ce70;
            font-size: 10pt;
            line-height: 1.1;
            font-weight: 700;
            text-transform: uppercase;
        }

        .vertical-rating {
            position: absolute;
            z-index: 2;
            top: 126mm;
            left: 12mm;
            width: 124mm;
            height: 72mm;
            padding-top: 6mm;
            border-top: .25mm solid #705b2b;
            text-align: center;
        }

        .vertical-qr {
            width: 44mm;
            height: 44mm;
            margin: 0 auto;
            padding: 1mm;
            overflow: hidden;
            color: #111;
            background: #fff;
            border: .45mm solid #d4a53b;
            border-radius: 2mm;
            font-size: 8pt;
            line-height: 41mm;
            text-align: center;
        }

        .vertical-qr img {
            display: block;
            width: 100%;
            height: 100%;
        }

        .vertical-code {
            display: inline-block;
            margin-top: 4mm;
            padding: 1.3mm 6.5mm;
            color: #f2ce70;
            border: .35mm solid #d4a53b;
            border-radius: 1.5mm;
            font-size: 15pt;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 2.1pt;
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
                                <td class="slot--horizontal"></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            @endif
        </section>
    @endforeach
</body>
</html>