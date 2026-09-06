<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CustomerRating;
use App\Models\CustomerRatingReward;
use App\Models\InternalDeviceMarker;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\GuestBrowserTokenService;
use App\Services\InternalDeviceMarkerService;
use App\Services\RatingQrService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QrRatingFormTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_active_qr_with_active_attendance_can_open_and_submit_rating(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);
        $shortUrl = route('qr-rating.short.show', ['publicRatingCode' => $employee->public_rating_code]);

        $this->get(route('qr-rating.show', ['token' => $rawToken]))
            ->assertRedirect($shortUrl);

        $response = $this->get($shortUrl);

        $response
            ->assertOk()
            ->assertCookie('guest_rating_token')
            ->assertSee('ĐÁNH GIÁ DỊCH VỤ')
            ->assertSee($employee->name)
            ->assertSee('data-qr-rating-option', false)
            ->assertSee('value="bad"', false)
            ->assertSee('value="average"', false)
            ->assertSee('value="good"', false);
        $response
            ->assertSee('/css/qr-rating.css', false)
            ->assertSee('/js/qr-rating.js', false)
            ->assertSee('/images/maxsim-logo.png', false)
            ->assertDontSee('[::1]:5173', false)
            ->assertDontSee('localhost:5173', false)
            ->assertDontSee('/@vite/client', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));

        $this->withCookie('guest_rating_token', $this->guestCookie('open-submit'))
            ->post(route('qr-rating.store', ['token' => $rawToken]), [
                'rating' => 'good',
                'comment' => 'Nhan vien ho tro tot',
            ])
            ->assertRedirect(route('qr-rating.thank-you'))
            ->assertSessionHas('rating_success');

        $this->assertSame(1, CustomerRating::count());
        $this->assertSame(CustomerRating::RATING_GOOD, CustomerRating::first()->rating);

        $fallbackResponse = $this->get(route('qr-rating.thank-you'))
            ->assertOk()
            ->assertSee('MAXSIM')
            ->assertSee('Ý KIẾN CỦA QUÝ KHÁCH ĐÃ ĐƯỢC GHI NHẬN.');
        $this->assertStringContainsString('no-store', $fallbackResponse->headers->get('Cache-Control'));
    }

    public function test_ajax_submit_returns_success_without_redirect(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);

        $this->withCookie('guest_rating_token', $this->guestCookie('ajax-submit'))
            ->postJson(route('qr-rating.short.store', ['publicRatingCode' => $employee->public_rating_code]), [
                'rating' => 'average',
                'comment' => null,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Ý kiến của Quý khách đã được ghi nhận.')
            ->assertJsonPath('thank_you_url', route('qr-rating.thank-you'));

        $this->assertSame(1, CustomerRating::count());
    }

    public function test_locked_old_or_wrong_qr_token_cannot_submit(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);

        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);
        app(RatingQrService::class)->disableForUser($employee, User::factory()->create(['role' => 'admin']));

        $this->postWithGuest($rawToken, 'locked-browser', 'good')
            ->assertSessionHas('qr_error_modal');

        [$employee, $oldToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);
        app(RatingQrService::class)->regenerateForUser($employee, User::factory()->create(['role' => 'admin']));

        $this->postWithGuest($oldToken, 'old-browser', 'good')
            ->assertSessionHas('qr_error_modal');

        $this->postWithGuest('wrong-token', 'wrong-browser', 'good')
            ->assertSessionHas('qr_error_modal');

        $this->assertSame(0, CustomerRating::count());
    }

    public function test_employee_without_checkin_does_not_create_rating(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));
        [, $rawToken] = $this->employeeWithRatingQr();

        $this->postWithGuest($rawToken, 'no-checkin-browser', 'good')
            ->assertSessionHas('qr_error_modal');

        $this->assertSame(0, CustomerRating::count());
    }

    public function test_internal_session_does_not_see_customer_form(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $employee->branch_id]);

        $this->actingAs($staff)
            ->get(route('qr-rating.show', ['token' => $rawToken]))
            ->assertOk()
            ->assertSee('Thiết bị nội bộ không thể gửi đánh giá.')
            ->assertDontSee('data-qr-rating-form', false);

        $this->actingAs($staff)
            ->post(route('qr-rating.store', ['token' => $rawToken]), [
                'rating' => 'good',
                'comment' => 'Bypass JavaScript',
            ])
            ->assertForbidden()
            ->assertDontSee('data-qr-rating-form', false);

        $this->assertSame(0, CustomerRating::count());
    }

    public function test_internal_device_marker_blocks_qr_form_after_logout(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);
        $staff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $employee->branch_id,
            'password' => bcrypt('password'),
        ]);

        $loginResponse = $this->post(route('login'), [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect();
        $loginResponse->assertCookie(InternalDeviceMarkerService::COOKIE_NAME);
        $marker = collect($loginResponse->headers->getCookies())
            ->first(fn($cookie) => $cookie->getName() === InternalDeviceMarkerService::COOKIE_NAME)
            ?->getValue();
        $markerCookie = collect($loginResponse->headers->getCookies())
            ->first(fn($cookie) => $cookie->getName() === InternalDeviceMarkerService::COOKIE_NAME);

        $this->assertNotEmpty($marker);
        $this->assertSame('/', $markerCookie->getPath());
        $this->assertTrue($markerCookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $markerCookie->getSameSite()));
        $this->assertGreaterThan(now()->addDays(360)->timestamp, $markerCookie->getExpiresTime());
        $this->assertSame(1, InternalDeviceMarker::count());
        $this->assertSame(64, strlen(InternalDeviceMarker::first()->token_hash));
        $this->assertNotSame($marker, InternalDeviceMarker::first()->token_hash);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->withCookie(InternalDeviceMarkerService::COOKIE_NAME, $marker)
            ->get(route('qr-rating.show', ['token' => $rawToken]))
            ->assertOk()
            ->assertSee('Thiết bị nội bộ không thể gửi đánh giá.')
            ->assertDontSee('data-qr-rating-form', false);
    }

    public function test_internal_device_marker_cookie_is_secure_on_https(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'password' => bcrypt('password'),
        ]);

        $loginResponse = $this
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withServerVariables(['HTTPS' => 'on', 'SERVER_PORT' => 443])
            ->post(route('login'), [
                'email' => $staff->email,
                'password' => 'password',
            ])
            ->assertRedirect();

        $markerCookie = collect($loginResponse->headers->getCookies())
            ->first(fn($cookie) => $cookie->getName() === InternalDeviceMarkerService::COOKIE_NAME);

        $this->assertNotNull($markerCookie);
        $this->assertTrue($markerCookie->isSecure());
    }

    public function test_manager_login_creates_internal_marker(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'password' => bcrypt('password'),
        ]);

        $this->post(route('login'), [
            'email' => $manager->email,
            'password' => 'password',
        ])
            ->assertRedirect()
            ->assertCookie(InternalDeviceMarkerService::COOKIE_NAME);

        $this->assertSame(1, InternalDeviceMarker::where('user_id', $manager->id)->count());
    }

    public function test_guest_browser_token_cookie_is_persistent_and_reused(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);
        $url = route('qr-rating.short.show', ['publicRatingCode' => $employee->public_rating_code]);

        $first = $this->get($url)->assertOk()->assertCookie('guest_rating_token');
        $cookie = collect($first->headers->getCookies())
            ->first(fn($cookie) => $cookie->getName() === 'guest_rating_token');

        $this->assertNotNull($cookie);
        $this->assertSame('/', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
        $this->assertGreaterThan(now()->addDays(360)->timestamp, $cookie->getExpiresTime());

        $this->withCookie('guest_rating_token', $cookie->getValue())
            ->get($url)
            ->assertOk()
            ->assertCookieMissing('guest_rating_token');
    }

    public function test_duplicate_guest_get_short_route_blocks_form_and_opens_modal(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee] = $this->employeeWithRatingQr();
        $attendance = $this->activeNormalAttendance($employee, $now);
        $guestToken = $this->guestCookie('duplicate-get-short');
        $this->createExistingRating($employee, $attendance, $guestToken, $now->toDateString());

        $response = $this->withCookie('guest_rating_token', $guestToken)
            ->get(route('qr-rating.short.show', ['publicRatingCode' => $employee->public_rating_code]));

        $response
            ->assertOk()
            ->assertSee('Bạn đã gửi đánh giá cho nhân viên này hôm nay rồi.')
            ->assertSee('Cảm ơn bạn đã phản hồi!')
            ->assertSee('data-qr-error-modal', false)
            ->assertSee('data-open="true"', false)
            ->assertSee('Đóng')
            ->assertDontSee('data-qr-rating-form', false)
            ->assertDontSee('data-qr-rating-submit', false)
            ->assertDontSee('data-qr-rating-comment', false);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_duplicate_guest_legacy_route_blocks_form_and_opens_modal(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $attendance = $this->activeNormalAttendance($employee, $now);
        $guestToken = $this->guestCookie('duplicate-get-legacy');
        $this->createExistingRating($employee, $attendance, $guestToken, $now->toDateString());

        $response = $this->withCookie('guest_rating_token', $guestToken)
            ->get(route('qr-rating.show', ['token' => $rawToken]));

        $response
            ->assertOk()
            ->assertSee('Bạn đã gửi đánh giá cho nhân viên này hôm nay rồi.')
            ->assertSee('data-qr-error-modal', false)
            ->assertSee('data-open="true"', false)
            ->assertDontSee('data-qr-rating-form', false);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Pragma'));
    }

    public function test_tampered_internal_device_marker_blocks_form_and_submit(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);

        $this->withCookie(InternalDeviceMarkerService::COOKIE_NAME, 'tampered-marker')
            ->get(route('qr-rating.show', ['token' => $rawToken]))
            ->assertOk()
            ->assertSee('Thiết bị nội bộ không thể gửi đánh giá.')
            ->assertDontSee('data-qr-rating-form', false);

        $this->withCookie(InternalDeviceMarkerService::COOKIE_NAME, 'tampered-marker')
            ->withCookie('guest_rating_token', $this->guestCookie('tampered-marker'))
            ->post(route('qr-rating.store', ['token' => $rawToken]), [
                'rating' => 'good',
                'comment' => 'Bypass JavaScript',
            ])
            ->assertForbidden();

        $this->assertSame(0, CustomerRating::count());
    }

    public function test_internal_device_get_has_priority_over_duplicate_guest_token(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $attendance = $this->activeNormalAttendance($employee, $now);
        $guestToken = $this->guestCookie('internal-priority');
        $this->createExistingRating($employee, $attendance, $guestToken, $now->toDateString());

        $response = $this->withCookie(InternalDeviceMarkerService::COOKIE_NAME, 'tampered-marker')
            ->withCookie('guest_rating_token', $guestToken)
            ->get(route('qr-rating.show', ['token' => $rawToken]));

        $response
            ->assertOk()
            ->assertSee('Thiết bị nội bộ không thể gửi đánh giá.')
            ->assertSee('data-qr-internal-modal', false)
            ->assertSee('data-open="true"', false)
            ->assertDontSee('Bạn đã gửi đánh giá cho nhân viên này hôm nay rồi.')
            ->assertDontSee('data-qr-rating-form', false);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_duplicate_browser_employee_attendance_is_rejected(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);

        $this->postWithGuest($rawToken, 'duplicate-browser', 'bad')
            ->assertRedirect(route('qr-rating.thank-you'));

        $this->postWithGuest($rawToken, 'duplicate-browser', 'good')
            ->assertSessionHas('qr_error_modal');

        $this->assertSame(1, CustomerRating::count());
    }

    public function test_bad_average_and_good_submit_expected_values(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        $branch = Branch::create(['name' => 'Chi nhanh']);

        foreach (['bad', 'average', 'good'] as $rating) {
            [$employee, $rawToken] = $this->employeeWithRatingQr($branch);
            $this->activeNormalAttendance($employee, $now);

            $this->postWithGuest($rawToken, 'browser-' . $rating, $rating)
                ->assertRedirect(route('qr-rating.thank-you'));
        }

        $this->assertSame(['bad', 'average', 'good'], CustomerRating::orderBy('id')->pluck('rating')->all());
    }

    public function test_empty_comment_can_be_submitted(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);

        $this->withCookie('guest_rating_token', $this->guestCookie('empty-comment'))
            ->post(route('qr-rating.store', ['token' => $rawToken]), [
                'rating' => 'average',
            ])
            ->assertRedirect(route('qr-rating.thank-you'));

        $this->assertNull(CustomerRating::first()->comment);
    }

    public function test_retry_does_not_create_duplicate_rating_or_reward(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);

        $this->postWithGuest($rawToken, 'retry-browser', 'good')
            ->assertRedirect(route('qr-rating.thank-you'));
        $this->postWithGuest($rawToken, 'retry-browser', 'good')
            ->assertSessionHas('qr_error_modal');

        $this->assertSame(1, CustomerRating::count());
        $this->assertSame(1, CustomerRatingReward::count());
        $this->assertSame(1, (int) DB::table('customer_rating_reward_counters')->sum('rewarded_good_count'));
    }

    public function test_same_guest_employee_business_date_is_rejected_even_with_different_attendance(): void
    {
        $now = Carbon::parse('2026-06-25 14:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $guestToken = $this->guestCookie('daily-duplicate');
        $oldAttendance = $this->activeNormalAttendance($employee, $now->copy()->setTime(8, 0));
        $oldAttendance->forceFill([
            'checkout_at' => $now->copy()->setTime(11, 0),
            'status' => 'completed',
        ])->save();
        $this->createExistingRating($employee, $oldAttendance, $guestToken, $now->toDateString());
        $this->activeNormalAttendance($employee, $now);

        $this->withCookie('guest_rating_token', $guestToken)
            ->post(route('qr-rating.store', ['token' => $rawToken]), [
                'rating' => 'good',
                'comment' => 'Lan hai cung ngay',
            ])
            ->assertSessionHas('qr_error_modal');

        $this->assertSame(1, CustomerRating::count());
        $this->assertSame(0, CustomerRatingReward::count());
        $this->assertSame(0, (int) DB::table('customer_rating_reward_counters')->sum('rewarded_good_count'));
    }

    public function test_same_guest_employee_different_business_date_is_allowed(): void
    {
        $today = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($today);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $guestToken = $this->guestCookie('different-day');
        $yesterdayAttendance = $this->activeNormalAttendance($employee, $today->copy()->subDay());
        $yesterdayAttendance->forceFill([
            'checkout_at' => $today->copy()->subDay()->setTime(17, 0),
            'status' => 'completed',
        ])->save();
        $this->createExistingRating($employee, $yesterdayAttendance, $guestToken, $today->copy()->subDay()->toDateString());
        $this->activeNormalAttendance($employee, $today);

        $this->withCookie('guest_rating_token', $guestToken)
            ->post(route('qr-rating.store', ['token' => $rawToken]), [
                'rating' => 'average',
                'comment' => 'Ngay moi',
            ])
            ->assertRedirect(route('qr-rating.thank-you'));

        $this->assertSame(2, CustomerRating::count());
    }

    public function test_same_guest_employee_different_business_date_can_open_form(): void
    {
        $today = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($today);
        [$employee] = $this->employeeWithRatingQr();
        $guestToken = $this->guestCookie('different-day-get');
        $yesterdayAttendance = $this->activeNormalAttendance($employee, $today->copy()->subDay());
        $yesterdayAttendance->forceFill([
            'checkout_at' => $today->copy()->subDay()->setTime(17, 0),
            'status' => 'completed',
        ])->save();
        $this->createExistingRating($employee, $yesterdayAttendance, $guestToken, $today->copy()->subDay()->toDateString());
        $this->activeNormalAttendance($employee, $today);

        $this->withCookie('guest_rating_token', $guestToken)
            ->get(route('qr-rating.short.show', ['publicRatingCode' => $employee->public_rating_code]))
            ->assertOk()
            ->assertSee('data-qr-rating-form', false)
            ->assertDontSee('Bạn đã gửi đánh giá cho nhân viên này hôm nay rồi.');
    }

    public function test_guest_can_still_rate_another_employee_when_existing_rules_allow_it(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        $branch = Branch::create(['name' => 'Chi nhanh']);
        [$employeeA] = $this->employeeWithRatingQr($branch);
        [$employeeB] = $this->employeeWithRatingQr($branch);
        $attendanceA = $this->activeNormalAttendance($employeeA, $now);
        $this->activeNormalAttendance($employeeB, $now);
        $guestToken = $this->guestCookie('another-employee');
        $this->createExistingRating($employeeA, $attendanceA, $guestToken, $now->toDateString());

        $this->withCookie('guest_rating_token', $guestToken)
            ->get(route('qr-rating.short.show', ['publicRatingCode' => $employeeB->public_rating_code]))
            ->assertOk()
            ->assertSee('data-qr-rating-form', false)
            ->assertDontSee('Bạn đã gửi đánh giá cho nhân viên này hôm nay rồi.');
    }

    public function test_post_duplicate_returns_friendly_message_without_creating_rating_or_reward(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $attendance = $this->activeNormalAttendance($employee, $now);
        $guestToken = $this->guestCookie('post-duplicate-friendly');
        $this->createExistingRating($employee, $attendance, $guestToken, $now->toDateString());

        $this->withCookie('guest_rating_token', $guestToken)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('qr-rating.store', ['token' => $rawToken]), [
                'rating' => 'good',
                'comment' => 'Bypass old tab',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Thiết bị này đã đánh giá nhân viên này hôm nay.');

        $this->assertSame(1, CustomerRating::count());
        $this->assertSame(0, CustomerRatingReward::count());
    }

    public function test_short_public_code_route_rejects_duplicate_rating_today(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee] = $this->employeeWithRatingQr();
        $attendance = $this->activeNormalAttendance($employee, $now);
        $guestToken = $this->guestCookie('short-duplicate');
        $this->createExistingRating($employee, $attendance, $guestToken, $now->toDateString());

        $this->withCookie('guest_rating_token', $guestToken)
            ->post(route('qr-rating.short.store', ['publicRatingCode' => $employee->public_rating_code]), [
                'rating' => 'good',
                'comment' => 'Short route duplicate',
            ])
            ->assertSessionHas('qr_error_modal');

        $this->assertSame(1, CustomerRating::count());
        $this->assertSame(0, CustomerRatingReward::count());
    }

    public function test_form_contains_mobile_first_markup_for_required_widths(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);

        $this->get(route('qr-rating.short.show', ['publicRatingCode' => $employee->public_rating_code]))
            ->assertOk()
            ->assertSee('viewport-fit=cover')
            ->assertSee('data-responsive-widths="320,375,390,430"', false)
            ->assertSee('Để bảo đảm đánh giá được ghi nhận đúng, vui lòng mở liên kết này bằng tab trình duyệt thông thường.')
            ->assertSee('TÔI ĐÃ MỞ TAB THƯỜNG')
            ->assertSee('data-qr-private-reload', false)
            ->assertDontSee('qr-alert error', false);

        $css = file_get_contents(public_path('css/qr-rating.css'));

        $this->assertStringContainsString('max-width: 430px', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere', $css);
        $this->assertStringContainsString('@media (max-width: 375px)', $css);
        $this->assertStringContainsString('@media (max-width: 340px)', $css);
        $this->assertStringContainsString('font-family: "Roboto Condensed", Arial, sans-serif', $css);
        $this->assertStringContainsString('.qr-thank-card', $css);
        $this->assertStringContainsString('.qr-rating-logo img', $css);
        $this->assertStringContainsString('object-fit: contain', $css);
        $this->assertStringNotContainsString('QR_LOGO_FIX', $css);
        $this->assertStringNotContainsString('position: absolute !important', $css);
        $this->assertStringNotContainsString('outline: 4px solid lime', $css);

        $logoSize = getimagesize(public_path('images/maxsim-logo.png'));
        $this->assertSame([316, 200], [$logoSize[0], $logoSize[1]]);

        $js = file_get_contents(public_path('js/qr-rating.js'));

        $this->assertStringContainsString("return 'suspected'", $js);
        $this->assertStringContainsString("return 'unknown'", $js);
        $this->assertStringContainsString("state !== 'suspected'", $js);
        $this->assertStringContainsString("data-private-blocked", $js);
        $this->assertStringContainsString("fetch(form.action", $js);
        $this->assertStringContainsString('window.location.replace(thankYouUrl())', $js);
        $this->assertStringContainsString('visualViewport', $js);
    }

    private function employeeWithRatingQr(?Branch $branch = null): array
    {
        $branch ??= Branch::create(['name' => 'Chi nhanh ' . uniqid()]);
        $employee = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'rating_qr_enabled' => true,
        ]);
        $token = app(RatingQrService::class)->getOrCreateForUser($employee);

        return [$employee->fresh('branch'), app(RatingQrService::class)->rawToken($token)];
    }

    private function activeNormalAttendance(User $employee, Carbon $now): Attendance
    {
        $shift = Shift::create([
            'name' => 'Ca thuong ' . uniqid(),
            'start_at' => '08:00:00',
            'end_at' => '17:00:00',
        ]);

        ShiftAssignment::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'work_date' => $now->toDateString(),
        ]);

        return Attendance::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'work_date' => $now->toDateString(),
            'checkin_at' => $now->copy()->setTime(8, 0),
            'status' => 'checked_in',
        ]);
    }

    private function postWithGuest(string $rawToken, string $browser, string $rating)
    {
        return $this->withCookie('guest_rating_token', $this->guestCookie($browser))
            ->post(route('qr-rating.store', ['token' => $rawToken]), [
                'rating' => $rating,
                'comment' => 'Noi dung danh gia',
            ]);
    }

    public function test_rating_screen_shows_last_four_digits_of_citizen_id(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $employee->update(['citizen_id' => '079201013957']);
        $this->activeNormalAttendance($employee, $now);

        $shortUrl = route('qr-rating.short.show', ['publicRatingCode' => $employee->public_rating_code]);

        $this->get($shortUrl)
            ->assertOk()
            ->assertSee('ID 3957');
    }

    private function guestCookie(string $suffix): string
    {
        return str_pad('guest-' . $suffix, 64, '-');
    }

    private function createExistingRating(User $employee, Attendance $attendance, string $guestToken, string $businessDate): CustomerRating
    {
        return CustomerRating::create([
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'shift_id' => $attendance->shift_id,
            'shift_assignment_id' => null,
            'attendance_id' => $attendance->id,
            'attendance_segment_id' => null,
            'rating_qr_token_id' => null,
            'work_date' => $businessDate,
            'business_date' => $businessDate,
            'rating' => CustomerRating::RATING_BAD,
            'comment' => null,
            'guest_browser_hash' => app(GuestBrowserTokenService::class)->hashToken($guestToken),
            'ip_hash' => null,
            'user_agent_hash' => null,
            'settings_snapshot' => [],
            'submitted_at' => Carbon::parse($businessDate . ' 09:00:00'),
        ]);
    }
}
