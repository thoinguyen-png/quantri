<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" translate="no">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="google" content="notranslate">
    <meta name="format-detection" content="telephone=no,email=no,address=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <title>{{ trim($__env->yieldContent('title', config('app.name', 'Maxsim'))) }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @include('layouts.partials.assets')
</head>

<body class="font-sans antialiased app-body">
    <div class="app-shell">
        @include('layouts.navigation')

        @isset($header)
            <header class="app-page-header">
                <div class="app-container app-page-header__inner">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main class="app-main">
            {{ $slot }}
        </main>

        <x-mobile-nav />

        @php
            $appModal = session('attendance_modal');

            if (!$appModal && session('success') && !request()->routeIs('dashboard')) {
                $appModal = [
                    'type' => 'success',
                    'title' => 'Thành công',
                    'message' => session('success'),
                ];
            }
        @endphp

        @if ($appModal)
            <div class="app-modal" role="dialog" aria-modal="true" aria-labelledby="app-modal-title">
                <div class="app-modal__backdrop" data-modal-close></div>
                <div class="app-modal__panel">
                    <div class="app-modal__icon app-modal__icon--{{ $appModal['type'] ?? 'success' }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9.2 16.2 4.9 11.9 3.5 13.3l5.7 5.7L21 7.2l-1.4-1.4-10.4 10.4Z" />
                        </svg>
                    </div>
                    <h2 id="app-modal-title" class="app-modal__title">
                        {{ $appModal['title'] ?? 'Thành công' }}
                    </h2>
                    <p class="app-modal__message">
                        {{ $appModal['message'] ?? 'Thao tác đã được ghi nhận.' }}
                    </p>
                    <button type="button" class="app-modal__button" data-modal-close>
                        Đã hiểu
                    </button>
                </div>
            </div>
        @endif
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            const appVersion = document.querySelector('meta[name="app-version"]')?.content || String(Date.now());
            let pwaRefreshing = false;
            let updateBanner = null;

            function hasDirtyForm() {
                return Array.from(document.querySelectorAll('form')).some(function (form) {
                    return Array.from(form.elements || []).some(function (element) {
                        if (!element.name || ['hidden', 'button', 'submit', 'reset'].includes(element.type)) {
                            return false;
                        }

                        if (['checkbox', 'radio'].includes(element.type)) {
                            return element.checked !== element.defaultChecked;
                        }

                        return element.value !== element.defaultValue;
                    });
                });
            }

            function showUpdateBanner() {
                if (updateBanner) {
                    return;
                }

                updateBanner = document.createElement('div');
                updateBanner.className = 'fixed inset-x-4 bottom-24 z-[80] mx-auto max-w-md rounded-2xl bg-slate-900 p-4 text-sm text-white shadow-2xl md:bottom-6';
                updateBanner.innerHTML = `
                    <div class="font-bold">Ứng dụng vừa được cập nhật.</div>
                    <div class="mt-1 text-slate-200">Bấm tải lại để dùng bản mới.</div>
                    <button type="button" class="mt-3 rounded-xl bg-white px-4 py-2 font-bold text-slate-900">
                        Tải lại
                    </button>
                `;
                updateBanner.querySelector('button').addEventListener('click', function () {
                    window.location.reload();
                });
                document.body.appendChild(updateBanner);
            }

            function reloadWhenSafe() {
                if (pwaRefreshing) {
                    return;
                }

                pwaRefreshing = true;

                if (hasDirtyForm()) {
                    showUpdateBanner();
                    return;
                }

                window.location.reload();
            }

            navigator.serviceWorker.addEventListener('controllerchange', function () {
                reloadWhenSafe();
            });

            navigator.serviceWorker.register('/sw.js?v=' + encodeURIComponent(appVersion), {
                updateViaCache: 'none',
            }).then(function (registration) {
                registration.update();

                registration.addEventListener('updatefound', function () {
                    const worker = registration.installing;

                    if (!worker) {
                        return;
                    }

                    worker.addEventListener('statechange', function () {
                        if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                            reloadWhenSafe();
                        }
                    });
                });
            });
        }

        (function () {
            const sessionStatusUrl = @json(route('auth.session-status', absolute: false));
            const expiredUrl = @json(route('session.expired', absolute: false));
            let checkingSession = false;

            async function ensureSessionIsAlive() {
                if (checkingSession) {
                    return;
                }

                checkingSession = true;

                try {
                    const response = await fetch(sessionStatusUrl + '?t=' + Date.now(), {
                        cache: 'no-store',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if ([401, 419].includes(response.status)) {
                        window.location.replace(expiredUrl);
                        return;
                    }

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();

                    if (data.authenticated === false) {
                        window.location.replace(expiredUrl);
                    }
                } catch (error) {
                    console.warn('Session check skipped because the request failed.', error);
                } finally {
                    checkingSession = false;
                }
            }

            window.addEventListener('pageshow', ensureSessionIsAlive);
            window.addEventListener('focus', ensureSessionIsAlive);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    ensureSessionIsAlive();
                }
            });
        })();

        @auth
            @if (auth()->user()->role === 'cashier')
                (function () {
                    const keepAliveUrl = @json(route('auth.session-keepalive', absolute: false));
                    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
                        || window.navigator.standalone === true;
                    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i.test(navigator.userAgent);

                    if (isStandalone || isMobile) {
                        return;
                    }

                    async function keepCashierDesktopSessionAlive() {
                        try {
                            await fetch(keepAliveUrl + '?t=' + Date.now(), {
                                cache: 'no-store',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });
                        } catch (error) {
                            // Session status checker will handle expired sessions.
                        }
                    }

                    keepCashierDesktopSessionAlive();
                    window.setInterval(keepCashierDesktopSessionAlive, 5 * 60 * 1000);
                    window.addEventListener('focus', keepCashierDesktopSessionAlive);
                    document.addEventListener('visibilitychange', function () {
                        if (!document.hidden) {
                            keepCashierDesktopSessionAlive();
                        }
                    });
                })();
            @endif
        @endauth

        document.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                const modal = button.closest('.app-modal');

                if (modal) {
                    modal.remove();
                }
            });
        });

        @auth
            @if (in_array(auth()->user()->role, ['admin', 'manager'], true))
                (function () {
                    const endpoint = @json(route('notifications.pending-count', absolute: false));
                    let isLoading = false;

                    function formatCount(count) {
                        return count > 99 ? '99+' : String(count);
                    }

                    function renderPendingCount(count) {
                        document.querySelectorAll('[data-pending-target]').forEach(function (target) {
                            target.classList.toggle('has-badge', count > 0);
                        });

                        document.querySelectorAll('[data-pending-badge]').forEach(function (badge) {
                            badge.classList.toggle('is-hidden', count <= 0);
                            badge.setAttribute('aria-label', count + ' đơn chờ xử lý');
                        });

                        document.querySelectorAll('[data-pending-count]').forEach(function (counter) {
                            counter.textContent = formatCount(count);
                        });
                    }

                    function renderPendingType(type, count) {
                        document.querySelectorAll('[data-pending-type="' + type + '"]').forEach(function (counter) {
                            counter.classList.toggle('is-hidden', count <= 0);
                            counter.textContent = formatCount(count);
                        });
                    }

                    async function refreshPendingCount() {
                        if (isLoading) {
                            return;
                        }

                        isLoading = true;

                        try {
                            const response = await fetch(endpoint + '?t=' + Date.now(), {
                                cache: 'no-store',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (!response.ok) {
                                throw new Error('Không tải được thông báo');
                            }

                            const data = await response.json();
                            renderPendingCount(Number(data.count || 0));
                            renderPendingType('supplements', Number(data.supplements || 0));
                            renderPendingType('leaves', Number(data.leaves || 0));
                        } catch (error) {
                            console.error(error);
                        } finally {
                            isLoading = false;
                        }
                    }

                    refreshPendingCount();
                    window.setInterval(refreshPendingCount, 5000);
                    window.addEventListener('focus', refreshPendingCount);
                    document.addEventListener('visibilitychange', function () {
                        if (!document.hidden) {
                            refreshPendingCount();
                        }
                    });
                })();
            @endif
        @endauth
    </script>
</body>

</html>
