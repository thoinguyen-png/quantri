<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Không đủ quyền truy cập</title>
    @include('layouts.partials.assets')
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: #0f172a;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
        }

        .error-page {
            display: grid;
            min-height: 100vh;
            place-items: center;
            padding: 24px;
        }

        .error-card {
            width: min(100%, 420px);
            padding: 28px;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.14);
        }

        .error-icon {
            display: grid;
            width: 58px;
            height: 58px;
            margin: 0 auto 16px;
            place-items: center;
            color: #dc2626;
            background: #fef2f2;
            border-radius: 18px;
        }

        .error-icon svg {
            width: 30px;
            height: 30px;
            fill: currentColor;
        }

        .error-code {
            margin: 0;
            color: #dc2626;
            font-size: 14px;
            font-weight: 900;
        }

        .error-title {
            margin: 8px 0 0;
            font-size: 28px;
            font-weight: 900;
            line-height: 1.15;
        }

        .error-message {
            margin: 12px 0 0;
            color: #64748b;
            font-size: 15px;
            line-height: 1.6;
        }

        .error-actions {
            display: grid;
            gap: 10px;
            margin-top: 24px;
        }

        .home-button {
            display: inline-flex;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            padding: 0 18px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 900;
            text-decoration: none;
            background: #0f172a;
            border-radius: 16px;
        }

        .home-button:focus-visible {
            outline: 3px solid rgba(37, 99, 235, 0.3);
            outline-offset: 3px;
        }

        @media (max-width: 480px) {
            .error-page {
                align-items: start;
                padding: 18px;
                padding-top: 14vh;
            }

            .error-card {
                padding: 22px;
                border-radius: 20px;
            }

            .error-title {
                font-size: 24px;
            }

            .error-message {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <main class="error-page">
        <section class="error-card" aria-labelledby="error-title">
            <div class="error-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2 3.5 5.75v6.45c0 5.1 3.55 8.75 8.5 9.8 4.95-1.05 8.5-4.7 8.5-9.8V5.75L12 2Zm.85 5.5v6.25h-1.7V7.5h1.7Zm0 8v1.9h-1.7v-1.9h1.7Z"/>
                </svg>
            </div>

            <p class="error-code">403</p>
            <h1 id="error-title" class="error-title">Không đủ quyền truy cập</h1>
            <p class="error-message">
                {{ $exception->getMessage() ?: 'Tài khoản của bạn không có quyền mở trang này.' }}
            </p>

            <div class="error-actions">
                <a href="{{ url('/') }}" class="home-button">
                    Quay về trang chủ
                </a>
            </div>
        </section>
    </main>
</body>
</html>
