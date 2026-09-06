@php
    $pdfStaffName = trim((string) ($card['name'] ?? ''));
    $pdfStaffNameLength = mb_strlen($pdfStaffName);

    $pdfNameClass = match (true) {
        $pdfStaffNameLength > 30 => 'name-very-long',
        $pdfStaffNameLength > 22 => 'name-long',
        default => '',
    };

    $pdfPosition = trim((string) ($card['position'] ?? 'Nhân viên'));
@endphp

@if ($template === 'horizontal')
    <article class="staff-card staff-card--horizontal">
        <table class="horizontal-layout">
            <tr>
                <td class="horizontal-logo">
                    @if (! empty($card['branch_logo_url']))
                        <img src="{{ $card['branch_logo_url'] }}" alt="Logo cơ sở">
                    @endif
                </td>

                <td class="horizontal-info">
                    <div class="horizontal-name {{ $pdfNameClass }}">
                        {{ mb_strtoupper($pdfStaffName, 'UTF-8') }}
                    </div>

                    <div class="horizontal-position">
                        {{ mb_strtoupper($pdfPosition, 'UTF-8') }}
                    </div>

                    <div class="horizontal-code">
                        {{ $card['citizen_last4'] ?? '----' }}
                    </div>
                </td>

                <td class="horizontal-qr-cell">
                    <div class="horizontal-qr-box">
                        @if (! empty($card['qr_svg_url']))
                            <img src="{{ $card['qr_svg_url'] }}" alt="QR đánh giá">
                        @else
                            Chưa có QR
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </article>
@else
    <article class="staff-card staff-card--vertical">
        <div class="vertical-frame"></div>

        <header class="vertical-header">
            @if (! empty($card['maxsim_logo_url']))
                <img
                    class="vertical-maxsim"
                    src="{{ $card['maxsim_logo_url'] }}"
                    alt="MAXSIM"
                >
            @endif

            <div class="vertical-brand">
                @if (! empty($card['branch_logo_url']))
                    <img
                        class="vertical-brand-logo"
                        src="{{ $card['branch_logo_url'] }}"
                        alt="Logo cơ sở"
                    >
                @endif

                <table class="vertical-divider">
                    <tr>
                        <td><div class="vertical-divider-line"></div></td>
                        <td class="vertical-divider-star">★</td>
                        <td><div class="vertical-divider-line"></div></td>
                    </tr>
                </table>
            </div>
        </header>

        <table class="vertical-info">
            <tr>
                <td class="vertical-avatar-cell">
                    <div class="vertical-avatar">
                        @if (! empty($card['avatar_url']))
                            <img
                                src="{{ $card['avatar_url'] }}"
                                alt="Ảnh {{ $pdfStaffName }}"
                            >
                        @else
                            {{ mb_strtoupper(mb_substr($pdfStaffName, 0, 1), 'UTF-8') }}
                        @endif
                    </div>
                </td>

                <td class="vertical-identity">
                    <div class="vertical-name {{ $pdfNameClass }}">
                        {{ mb_strtoupper($pdfStaffName, 'UTF-8') }}
                    </div>

                    <div class="vertical-position">
                        {{ mb_strtoupper($pdfPosition, 'UTF-8') }}
                    </div>
                </td>
            </tr>
        </table>

        <section class="vertical-rating">
            <div class="vertical-qr">
                @if (! empty($card['qr_svg_url']))
                    <img src="{{ $card['qr_svg_url'] }}" alt="QR đánh giá">
                @else
                    Chưa có QR đánh giá
                @endif
            </div>

            <div class="vertical-code">
                {{ $card['citizen_last4'] ?? '----' }}
            </div>
        </section>
    </article>
@endif