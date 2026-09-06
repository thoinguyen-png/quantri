@php
    $pdfStaffName = trim((string) ($card['name'] ?? ''));

    if (
        $pdfStaffName !== ''
        && class_exists(\Normalizer::class)
    ) {
        $normalizedName = \Normalizer::normalize(
            $pdfStaffName,
            \Normalizer::FORM_C
        );

        if (is_string($normalizedName)) {
            $pdfStaffName = $normalizedName;
        }
    }

    $pdfStaffNameUpper = mb_strtoupper(
        $pdfStaffName,
        'UTF-8'
    );

    if (class_exists(\Normalizer::class)) {
        $normalizedUpperName = \Normalizer::normalize(
            $pdfStaffNameUpper,
            \Normalizer::FORM_C
        );

        if (is_string($normalizedUpperName)) {
            $pdfStaffNameUpper = $normalizedUpperName;
        }
    }

    $pdfStaffNameLength = mb_strlen(
        $pdfStaffName,
        'UTF-8'
    );

    $pdfNameClass = match (true) {
        $pdfStaffNameLength > 34 => 'name-very-long',
        $pdfStaffNameLength > 24 => 'name-long',
        default => '',
    };

    $pdfPosition = trim((string) ($card['position'] ?? ''));

    if (
        $pdfPosition !== ''
        && class_exists(\Normalizer::class)
    ) {
        $normalizedPosition = \Normalizer::normalize(
            $pdfPosition,
            \Normalizer::FORM_C
        );

        if (is_string($normalizedPosition)) {
            $pdfPosition = $normalizedPosition;
        }
    }

    $pdfPositionUpper = mb_strtoupper(
        $pdfPosition,
        'UTF-8'
    );
@endphp

@if ($template === 'horizontal')
    <article class="staff-card staff-card--horizontal">
        <div class="horizontal-layout">
            <div class="horizontal-logo">
                <table class="horizontal-logo-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="horizontal-logo-cell">
                            @if (! empty($card['branch_logo_url']))
                                <img src="{{ $card['branch_logo_url'] }}" alt="Logo cơ sở">
                            @endif
                        </td>
                    </tr>
                </table>
            </div>

            <div class="horizontal-info">
                <table class="horizontal-info-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="horizontal-info-cell">
                            <div class="horizontal-name {{ $pdfNameClass }}">
                                {{ $pdfStaffNameUpper }}
                            </div>

                            <table class="horizontal-divider-table" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="horizontal-divider-dot">&#9670;</td>
                                    <td class="horizontal-divider-line-cell"><div class="horizontal-divider-line"></div></td>
                                    <td class="horizontal-divider-dot">&#9670;</td>
                                </tr>
                            </table>

                            @if ($pdfPosition !== '')
                                <div class="horizontal-position">
                                    {{ $pdfPosition }}
                                </div>
                            @endif

                            <div class="horizontal-code">
                                {{ $card['citizen_last4'] ?? '----' }}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="horizontal-qr-cell">
                <table class="horizontal-qr-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="horizontal-qr-cell-inner">
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
            </div>
        </div>
    </article>
@else
    <article class="staff-card staff-card--vertical">
        <div class="vertical-frame"></div>
        <div class="vertical-inner-frame"></div>

        <img class="vertical-circuit vertical-circuit--left" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iNTAwIiB2aWV3Qm94PSIwIDAgMTAwIDUwMCI+PGcgc3Ryb2tlPSIjYzk5YjNiIiBzdHJva2Utd2lkdGg9IjIiIGZpbGw9Im5vbmUiIG9wYWNpdHk9IjAuMzIiPjxwYXRoIGQ9Ik0wLDQwIEw0NSw0MCBMNzAsNjUgTDcwLDEzMCBMNTAsMTUwIEw1MCwyMTAgTDc1LDIzNSBMNzUsMzIwIEw1MCwzNDUgTDUwLDQyMCBMMjUsNDQ1IEwwLDQ0NSIvPjxwYXRoIGQ9Ik0wLDkwIEwzMCw5MCBMNTUsMTE1IEw1NSwxODAgTDM1LDIwMCBMMzUsMjYwIEw2MCwyODUgTDYwLDM3MCBMMzUsMzk1IEwwLDM5NSIvPjxwYXRoIGQ9Ik0wLDE2MCBMMjAsMTYwIEw0MCwxODAgTDQwLDI0MCBMMjAsMjYwIEwyMCwzMzAgTDQ1LDM1NSBMNDUsNDYwIEwwLDQ2MCIvPjxwYXRoIGQ9Ik0wLDIzMCBMMTUsMjMwIEwzMCwyNDUgTDMwLDMwMCBMMTUsMzE1IEwxNSwzNjAgTDAsMzYwIi8+PGNpcmNsZSBjeD0iNzAiIGN5PSIxMzAiIHI9IjMuNSIgZmlsbD0iI2M5OWIzYiIvPjxjaXJjbGUgY3g9IjUwIiBjeT0iMjEwIiByPSIzLjUiIGZpbGw9IiNjOTliM2IiLz48Y2lyY2xlIGN4PSI3NSIgY3k9IjMyMCIgcj0iMy41IiBmaWxsPSIjYzk5YjNiIi8+PGNpcmNsZSBjeD0iNTUiIGN5PSIxODAiIHI9IjMuNSIgZmlsbD0iI2M5OWIzYiIvPjxjaXJjbGUgY3g9IjYwIiBjeT0iMzcwIiByPSIzLjUiIGZpbGw9IiNjOTliM2IiLz48Y2lyY2xlIGN4PSI0MCIgY3k9IjI0MCIgcj0iMy41IiBmaWxsPSIjYzk5YjNiIi8+PGNpcmNsZSBjeD0iNDUiIGN5PSI0NjAiIHI9IjMuNSIgZmlsbD0iI2M5OWIzYiIvPjxjaXJjbGUgY3g9IjMwIiBjeT0iMzAwIiByPSIzLjUiIGZpbGw9IiNjOTliM2IiLz48L2c+PC9zdmc+" alt="">
        <img class="vertical-circuit vertical-circuit--right" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iNTAwIiB2aWV3Qm94PSIwIDAgMTAwIDUwMCI+PGcgc3Ryb2tlPSIjYzk5YjNiIiBzdHJva2Utd2lkdGg9IjIiIGZpbGw9Im5vbmUiIG9wYWNpdHk9IjAuMzIiPjxwYXRoIGQ9Ik0xMDAsNDAgTDU1LDQwIEwzMCw2NSBMMzAsMTMwIEw1MCwxNTAgTDUwLDIxMCBMMjUsMjM1IEwyNSwzMjAgTDUwLDM0NSBMNTAsNDIwIEw3NSw0NDUgTDEwMCw0NDUiLz48cGF0aCBkPSJNMTAwLDkwIEw3MCw5MCBMNDUsMTE1IEw0NSwxODAgTDY1LDIwMCBMNjUsMjYwIEw0MCwyODUgTDQwLDM3MCBMNjUsMzk1IEwxMDAsMzk1Ii8+PHBhdGggZD0iTTEwMCwxNjAgTDgwLDE2MCBMNjAsMTgwIEw2MCwyNDAgTDgwLDI2MCBMODAsMzMwIEw1NSwzNTUgTDU1LDQ2MCBMMTAwLDQ2MCIvPjxwYXRoIGQ9Ik0xMDAsMjMwIEw4NSwyMzAgTDcwLDI0NSBMNzAsMzAwIEw4NSwzMTUgTDg1LDM2MCBMMTAwLDM2MCIvPjxjaXJjbGUgY3g9IjMwIiBjeT0iMTMwIiByPSIzLjUiIGZpbGw9IiNjOTliM2IiLz48Y2lyY2xlIGN4PSI1MCIgY3k9IjIxMCIgcj0iMy41IiBmaWxsPSIjYzk5YjNiIi8+PGNpcmNsZSBjeD0iMjUiIGN5PSIzMjAiIHI9IjMuNSIgZmlsbD0iI2M5OWIzYiIvPjxjaXJjbGUgY3g9IjQ1IiBjeT0iMTgwIiByPSIzLjUiIGZpbGw9IiNjOTliM2IiLz48Y2lyY2xlIGN4PSI0MCIgY3k9IjM3MCIgcj0iMy41IiBmaWxsPSIjYzk5YjNiIi8+PGNpcmNsZSBjeD0iNjAiIGN5PSIyNDAiIHI9IjMuNSIgZmlsbD0iI2M5OWIzYiIvPjxjaXJjbGUgY3g9IjU1IiBjeT0iNDYwIiByPSIzLjUiIGZpbGw9IiNjOTliM2IiLz48Y2lyY2xlIGN4PSI3MCIgY3k9IjMwMCIgcj0iMy41IiBmaWxsPSIjYzk5YjNiIi8+PC9nPjwvc3ZnPg==" alt="">

        <header class="vertical-header">
            <div class="vertical-maxsim-slot">
                @if (! empty($card['maxsim_logo_url']))
                    <img
                        class="vertical-maxsim"
                        src="{{ $card['maxsim_logo_url'] }}"
                        alt="MAXSIM"
                    >
                @endif
            </div>

            <div class="vertical-brand">
                @if (! empty($card['branch_logo_url']))
                    <img
                        class="vertical-brand-logo"
                        src="{{ $card['branch_logo_url'] }}"
                        alt="Logo cơ sở"
                    >
                @endif

                <img
                    class="vertical-divider-img"
                    src="data:image/png;base64,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"
                    alt=""
                >
            </div>
        </header>

        <section class="vertical-profile">
            <div class="vertical-avatar">
                @if (! empty($card['avatar_url']))
                    <img
                        src="{{ $card['avatar_url'] }}"
                        alt="Ảnh {{ $pdfStaffName }}"
                    >
                @else
                    {{ mb_strtoupper(
                        mb_substr($pdfStaffName, 0, 1),
                        'UTF-8'
                    ) }}
                @endif
            </div>
        </section>

        <section class="vertical-identity">
            <div class="vertical-name {{ $pdfNameClass }}">
                {{ $pdfStaffNameUpper }}
            </div>

            @if ($pdfPosition !== '')
                <div class="vertical-position">
                    {{ $pdfPositionUpper }}
                </div>
            @endif

            <table class="vertical-meta-table" align="center" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="vertical-meta-icon-cell">
                        <img class="vertical-meta-icon" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNkNGE1M2IiIHN0cm9rZS13aWR0aD0iMi4yIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjEwIi8+PHBvbHlsaW5lIHBvaW50cz0iMTIgNiAxMiAxMiAxNiAxNCIvPjwvc3ZnPg==" alt="Clock">
                    </td>
                    <td class="vertical-meta-text-cell">
                        {{ $card['work_hours'] ?? '11:00 - 05:00' }}
                    </td>
                </tr>
                <tr>
                    <td class="vertical-meta-icon-cell">
                        <img class="vertical-meta-icon" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNkNGE1M2IiIHN0cm9rZS13aWR0aD0iMi4yIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxwYXRoIGQ9Ik01IDRoNGwyIDUtMi41IDEuNWExMSAxMSAwIDAgMCA1IDVMMTUgMTNsNSAydjRhMiAyIDAgMCAxLTIgMkExNiAxNiAwIDAgMSAzIDZhMiAyIDAgMCAxIDItMnoiLz48L3N2Zz4=" alt="Phone">
                    </td>
                    <td class="vertical-meta-text-cell">
                        {{ $card['hotline'] ?? $card['phone_masked'] ?? '09xx-xxx-xxx' }}
                    </td>
                </tr>
            </table>
        </section>

        <section class="vertical-rating-section">
            <div class="vertical-rating-title">
                GHI NHẬN Ý KIẾN
            </div>

            <div class="vertical-qr-wrap">
                <div class="vertical-qr-box">
                    @if (! empty($card['qr_svg_url']))
                        <img src="{{ $card['qr_svg_url'] }}" alt="QR đánh giá">
                    @else
                        <span class="vertical-no-qr">Chưa có QR</span>
                    @endif
                </div>
            </div>

            <div class="vertical-code">
                {{ $card['citizen_last4'] ?? '----' }}
            </div>

            <div class="vertical-rating-hint">
                Hãy quét mã để gửi lời cảm ơn hoặc đóng góp ý kiến.
            </div>

            <div class="vertical-bottom-divider">
                <img class="vertical-bottom-divider-img" src="data:image/png;base64,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" alt="">
            </div>
        </section>

        <footer class="vertical-footer">
            <div class="vertical-slogan">
                {{ $card['slogan'] ?? 'HÀI LÒNG - QUAN TÂM - TRÂN TRỌNG' }}
            </div>
            <img class="vertical-sparkles-cluster" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSI+CiAgICA8IS0tIEJpZyBkaWFtb25kIHNwYXJrbGUgLS0+CiAgICA8cGF0aCBkPSJNMzQgMiBDMzQgMjIgMTggMzQgMiAzNCBDMTggMzQgMzQgNDYgMzQgNjYgQzM0IDQ2IDUwIDM0IDY2IDM0IEM1MCAzNCAzNCAyMiAzNCAyIFoiIGZpbGw9IiNmNGNmNjciIG9wYWNpdHk9IjAuODUiLz4KICAgIDwhLS0gU21hbGwgZGlhbW9uZCBzcGFya2xlIC0tPgogICAgPHBhdGggZD0iTTU4IDMyIEM1OCA0MiA0OCA1MCAzOCA1MCBDNDggNTAgNTggNTggNTggNjggQzU4IDU4IDY4IDUwIDc4IDUwIEM2OCA1MCA1OCA0MiA1OCAzMiBaIiBmaWxsPSIjZmZmZmZmIiBvcGFjaXR5PSIwLjkiLz4KPC9zdmc+" alt="">
            <div class="vertical-sparkle-dot">&#10022;</div>
        </footer>
    </article>
@endif
