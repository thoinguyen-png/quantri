<x-app-layout>
    <style>
        .app-main {
            padding-bottom: 0;
        }
    </style>

    <div class="qr-display-page">
        <div class="qr-display-page__watermark" aria-hidden="true">
            <img src="{{ asset('icons/logoMaxSim.png') }}" alt="">
        </div>

        <section class="qr-display-page__desktop" aria-label="Mã QR chấm công">
            <div class="qr-display-card">
                <img class="qr-display-card__image" data-qr-image alt="QR chấm công bên trái">
            </div>

            <div class="qr-display-clock">
                <div class="qr-display-clock__time" data-current-time>--:--</div>
                <div class="qr-display-clock__date" data-current-date>Đang tải ngày</div>
                <div class="qr-display-clock__status">
                    QR mới sau <strong data-countdown>10</strong>s
                </div>
            </div>

            <div class="qr-display-card">
                <img class="qr-display-card__image" data-qr-image alt="QR chấm công bên phải">
            </div>
        </section>

        <section class="qr-display-page__mobile" aria-label="Mã QR chấm công">
            <div class="qr-mobile-header">
                <span>Mã QR chấm công</span>
                <strong data-current-time>--:--</strong>
                <small data-current-date>Đang tải ngày</small>
            </div>

            <div class="qr-mobile-card">
                <img class="qr-mobile-card__image" data-qr-image alt="QR chấm công">
            </div>

            <div class="qr-mobile-status">
                <span>Làm mới tự động</span>
                <strong><span data-countdown>10</span>s</strong>
            </div>
        </section>
    </div>

    <script>
        (function () {
            let secondsLeft = 10;
            const qrImages = document.querySelectorAll('[data-qr-image]');
            const countdownEls = document.querySelectorAll('[data-countdown]');
            const timeEls = document.querySelectorAll('[data-current-time]');
            const dateEls = document.querySelectorAll('[data-current-date]');

            function setText(elements, text) {
                elements.forEach(function (element) {
                    element.textContent = text;
                });
            }

            function updateClock() {
                const now = new Date();
                setText(timeEls, now.toLocaleTimeString('vi-VN', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                }));
                setText(dateEls, new Intl.DateTimeFormat('vi-VN', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }).format(now));
            }

            async function loadQr() {
                try {
                    const response = await fetch('{{ route('qr.generate', absolute: false) }}?t=' + Date.now(), {
                        method: 'GET',
                        cache: 'no-store',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Không tải được mã QR');
                    }

                    const data = await response.json();

                    qrImages.forEach(function (image) {
                        image.src = data.qr;
                    });

                    secondsLeft = data.seconds || 10;
                    setText(countdownEls, secondsLeft);
                } catch (error) {
                    console.error(error);
                    setText(countdownEls, 'Lỗi');
                }
            }

            updateClock();
            loadQr();

            setInterval(updateClock, 1000);
            setInterval(function () {
                secondsLeft--;

                if (secondsLeft <= 0) {
                    setText(countdownEls, '...');
                    loadQr();
                    return;
                }

                setText(countdownEls, secondsLeft);
            }, 1000);
        })();
    </script>
</x-app-layout>
