<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaCacheRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_dashboard_html_is_not_cacheable(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }

    public function test_service_worker_keeps_rating_and_private_routes_network_first(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("CACHE_SCHEMA_VERSION = 'rating-private-v3'", $serviceWorker);
        $this->assertStringContainsString('/^\\/build\\/assets\\//', $serviceWorker);
        $this->assertStringContainsString('/^\\/css\\/qr-rating\\.css$/', $serviceWorker);
        $this->assertStringContainsString('/^\\/js\\/qr-rating\\.js$/', $serviceWorker);
        $this->assertStringContainsString('/^\\/images\\/maxsim-logo\\.png$/', $serviceWorker);
        $this->assertStringContainsString('/^\\/icons\\//', $serviceWorker);
        $this->assertStringContainsString('/^\\/dashboard/', $serviceWorker);
        $this->assertStringContainsString('/^\\/my-ratings/', $serviceWorker);
        $this->assertStringContainsString('/^\\/ratings\\/feed/', $serviceWorker);
        $this->assertStringContainsString('/^\\/qr-rating/', $serviceWorker);
        $this->assertStringContainsString('/^\\/rating-thank-you/', $serviceWorker);
        $this->assertStringContainsString('/^\\/rating-notifications/', $serviceWorker);
        $this->assertStringContainsString('/^\\/rating-qrs/', $serviceWorker);
        $this->assertStringContainsString("fetch(request, { cache: 'no-store' })", $serviceWorker);
        $this->assertStringContainsString("fetch(request, { cache: 'no-cache' })", $serviceWorker);
    }
}
