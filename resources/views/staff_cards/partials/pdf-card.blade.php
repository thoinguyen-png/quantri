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
            <table class="vertical-rating-table" align="center" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="vertical-scanner-cell">
                        <img class="vertical-scanner-icon" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTMwIiBmaWxsPSJub25lIiBzdHJva2U9IiNkNGE1M2IiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CiAgICA8IS0tIFBob25lIGJvZHkgLS0+CiAgICA8cmVjdCB4PSIxMiIgeT0iNiIgd2lkdGg9IjYyIiBoZWlnaHQ9IjExNCIgcng9IjExIiBzdHJva2U9IiNkNGE1M2IiIHN0cm9rZS13aWR0aD0iMy4yIiAvPgogICAgPCEtLSBTcGVha2VyIG5vdGNoIC0tPgogICAgPGxpbmUgeDE9IjM2IiB5MT0iMTUiIHgyPSI1MCIgeTI9IjE1IiBzdHJva2Utd2lkdGg9IjIuOCIgLz4KICAgIDwhLS0gSG9tZSBidXR0b24gLS0+CiAgICA8Y2lyY2xlIGN4PSI0MyIgY3k9IjEwNyIgcj0iMyIgc3Ryb2tlLXdpZHRoPSIyLjIiIC8+CiAgICA8IS0tIFZpZXdmaW5kZXIgY29ybmVycyAtLT4KICAgIDxwYXRoIGQ9Ik0yNCA0MCBMMjQgMzAgTDM0IDMwIiBzdHJva2Utd2lkdGg9IjIuNiIgLz4KICAgIDxwYXRoIGQ9Ik02MiA0MCBMNjIgMzAgTDUyIDMwIiBzdHJva2Utd2lkdGg9IjIuNiIgLz4KICAgIDxwYXRoIGQ9Ik0yNCA2NCBMMjQgNzQgTDM0IDc0IiBzdHJva2Utd2lkdGg9IjIuNiIgLz4KICAgIDxwYXRoIGQ9Ik02MiA2NCBMNjIgNzQgTDUyIDc0IiBzdHJva2Utd2lkdGg9IjIuNiIgLz4KICAgIDwhLS0gQ2FtZXJhIGxlbnMgLS0+CiAgICA8Y2lyY2xlIGN4PSI0MyIgY3k9IjUyIiByPSIxMSIgc3Ryb2tlLXdpZHRoPSIyLjUiIC8+CiAgICA8Y2lyY2xlIGN4PSI0MyIgY3k9IjUyIiByPSI1IiBzdHJva2Utd2lkdGg9IjEuOCIgLz4KICAgIDwhLS0gU3RhciBiYWRnZSBhdCBib3R0b20gcmlnaHQgLS0+CiAgICA8Y2lyY2xlIGN4PSI2OCIgY3k9Ijk0IiByPSIxMy41IiBmaWxsPSIjMGMwZDEyIiBzdHJva2U9IiNkNGE1M2IiIHN0cm9rZS13aWR0aD0iMi42IiAvPgogICAgPHBvbHlnb24gcG9pbnRzPSI2OCw4NSA3MC44LDkwLjUgNzYuOCw5MS40IDcyLjQsOTUuNyA3My41LDEwMS42IDY4LDk4LjggNjIuNSwxMDEuNiA2My42LDk1LjcgNTkuMiw5MS40IDY1LjIsOTAuNSIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjZDRhNTNiIiBzdHJva2Utd2lkdGg9IjEuNiIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgLz4KPC9zdmc+" alt="Scan">
                    </td>
                    <td class="vertical-qr-cell">
                        <div class="vertical-qr-box">
                            @if (! empty($card['qr_svg_url']))
                                <img src="{{ $card['qr_svg_url'] }}" alt="QR đánh giá">
                            @else
                                <span class="vertical-no-qr">Chưa có QR</span>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>

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
