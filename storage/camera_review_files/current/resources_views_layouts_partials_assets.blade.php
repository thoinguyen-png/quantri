@php
    $pwaAssetVersion = file_exists(public_path('build/manifest.json'))
        ? (string) filemtime(public_path('build/manifest.json'))
        : (string) filemtime(public_path('sw.js'));
@endphp

<meta name="app-version" content="{{ $pwaAssetVersion }}">
<link rel="manifest" href="/manifest.json?v={{ $pwaAssetVersion }}">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Maxsim">
<link rel="icon" type="image/png" href="{{ asset('icons/logoMaxSim.png') }}">
<link rel="shortcut icon" type="image/png" href="{{ asset('icons/logoMaxSim.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/logoMaxSim.png') }}">

@vite([
    'resources/css/app.css',
    'resources/css/navigation.css',
    'resources/css/dashboard.css',
    'resources/css/payroll.css',
    'resources/css/face-camera.css',
    'resources/css/attendance-supplements.css',
    'resources/css/attendance-records.css',
    'resources/js/app.js',
])
