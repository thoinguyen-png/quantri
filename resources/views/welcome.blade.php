<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Maxsim') }}</title>
    @include('layouts.partials.assets')
</head>
<body class="min-h-screen bg-slate-100 text-slate-950">
    <main class="min-h-screen flex items-center justify-center p-6">
        <section class="w-full max-w-md bg-white rounded-3xl shadow p-6 space-y-6">
            <div class="flex items-center gap-4">
                <x-application-logo class="w-14 h-14 fill-current text-slate-900" />
                <div>
                    <h1 class="text-2xl font-black">Maxsim</h1>
                    <p class="text-sm text-slate-500">Hệ thống chấm công nội bộ</p>
                </div>
            </div>

            @if (Route::has('login'))
                <div class="grid gap-3">
                    @auth
                        <a
                            href="{{ url('/dashboard') }}"
                            class="w-full rounded-2xl bg-blue-600 px-4 py-3 text-center font-bold text-white"
                        >
                            Vào bảng điều khiển
                        </a>
                    @else
                        <a
                            href="{{ route('login', absolute: false) }}"
                            class="w-full rounded-2xl bg-blue-600 px-4 py-3 text-center font-bold text-white"
                        >
                            Đăng nhập
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="w-full rounded-2xl bg-slate-100 px-4 py-3 text-center font-bold text-slate-900"
                            >
                                Đăng ký
                            </a>
                        @endif
                    @endauth
                </div>
            @endif
        </section>
    </main>
</body>
</html>
