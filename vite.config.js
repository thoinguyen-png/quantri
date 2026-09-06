import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/navigation.css', 'resources/css/dashboard.css', 'resources/css/payroll.css', 'resources/css/face-camera.css', 'resources/css/attendance-supplements.css', 'resources/css/attendance-records.css', 'resources/css/qr-rating.css', 'resources/css/rating-qr-management.css', 'resources/css/rating-notifications.css', 'resources/css/quick-onboarding-public.css', 'resources/js/app.js', 'resources/js/qr-rating.js', 'resources/js/rating-qr-management.js', 'resources/js/rating-notifications.js', 'resources/js/quick-onboarding.js', 'resources/js/quick-onboarding-public.js'],
            refresh: true,
        }),
    ],
});
