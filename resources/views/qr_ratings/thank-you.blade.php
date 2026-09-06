<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Cảm ơn Quý khách | MAXSIM</title>
    @php
        $qrCssUrl = '/css/qr-rating.css?v=' . filemtime(public_path('css/qr-rating.css'));
        $qrLogoUrl = '/images/maxsim-logo.png?v=' . filemtime(public_path('images/maxsim-logo.png'));
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700;800;900&display=swap">
    <link rel="preload" as="style" href="{{ $qrCssUrl }}">
    <link rel="stylesheet" href="{{ $qrCssUrl }}">
</head>
<body class="qr-rating-page qr-thank-page">
    <main class="qr-thank-shell">
        <section class="qr-thank-card" aria-labelledby="qr-thank-title">
            <div class="qr-thank-logo">
                <img src="{{ $qrLogoUrl }}" alt="MAXSIM">
            </div>

            <h1 id="qr-thank-title">CẢM ƠN QUÝ KHÁCH!</h1>

            @foreach ($message as $line)
                <p>{{ $line }}</p>
            @endforeach

            <p class="qr-thank-confirmation">{{ $confirmation }}</p>
        </section>
    </main>
</body>
</html>
