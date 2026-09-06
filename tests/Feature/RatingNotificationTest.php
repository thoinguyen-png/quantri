<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Notifications\CustomerRatingReceivedNotification;
use App\Services\RatingQrService;
use App\Services\RatingSubmissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_rating_creates_popup_notification_for_rated_employee_only(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        $branch = Branch::create(['name' => 'Chi nhanh A']);
        $otherBranch = Branch::create(['name' => 'Chi nhanh B']);
        [$employee, $rawToken] = $this->employeeWithRatingQr($branch);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $otherManager = User::factory()->create(['role' => 'manager', 'branch_id' => $otherBranch->id]);
        $admin = User::factory()->create(['role' => 'admin', 'rating_qr_enabled' => false]);
        $otherStaff = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $this->activeNormalAttendance($employee, $now);

        $this->submit($rawToken, 'guest-1', 'bad', 'Phục vụ chưa tốt.', $now);

        $this->assertSame(1, $employee->notifications()->where('type', CustomerRatingReceivedNotification::class)->count());
        $this->assertSame(0, $manager->notifications()->where('type', CustomerRatingReceivedNotification::class)->count());
        $this->assertSame(0, $admin->notifications()->where('type', CustomerRatingReceivedNotification::class)->count());
        $this->assertSame(0, $otherManager->notifications()->where('type', CustomerRatingReceivedNotification::class)->count());
        $this->assertSame(0, $otherStaff->notifications()->where('type', CustomerRatingReceivedNotification::class)->count());
    }

    public function test_polling_endpoint_returns_safe_payload_and_prioritizes_bad_rating(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);

        $this->submit($rawToken, 'guest-average', 'average', 'Ổn.', $now);
        $this->submit($rawToken, 'guest-bad', 'bad', 'Không hài lòng.', $now->copy()->addMinute());

        $pollResponse = $this->actingAs($employee)
            ->getJson(route('rating-notifications.recent'))
            ->assertOk()
            ->assertJsonPath('polling_interval_ms', 30000)
            ->assertJsonPath('notifications.0.rating', 'bad')
            ->assertJsonPath('notifications.0.priority', 'high');
        $this->assertStringContainsString('no-store', $pollResponse->headers->get('Cache-Control'));

        $response = $pollResponse->json('notifications');
        $this->assertCount(count(array_unique(array_column($response, 'id'))), $response);

        $this->assertArrayHasKey('employee_name', $response[0]);
        $this->assertArrayHasKey('reward_status_label', $response[0]);
        $this->assertArrayNotHasKey('branch_name', $response[0]);
        $this->assertArrayNotHasKey('guest_browser_hash', $response[0]);
        $this->assertArrayNotHasKey('ip_hash', $response[0]);
        $this->assertArrayNotHasKey('user_agent_hash', $response[0]);
    }

    public function test_only_rated_employee_polls_popup_notification(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        $branch = Branch::create(['name' => 'Chi nhanh A']);
        $otherBranch = Branch::create(['name' => 'Chi nhanh B']);
        [$employee, $rawToken] = $this->employeeWithRatingQr($branch);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $otherManager = User::factory()->create(['role' => 'manager', 'branch_id' => $otherBranch->id]);
        $admin = User::factory()->create(['role' => 'admin', 'rating_qr_enabled' => false]);
        $this->activeNormalAttendance($employee, $now);

        $this->submit($rawToken, 'guest-scope', 'good', null, $now);

        $this->actingAs($employee)
            ->getJson(route('rating-notifications.recent'))
            ->assertOk()
            ->assertJsonCount(1, 'notifications');

        $this->actingAs($manager)
            ->getJson(route('rating-notifications.recent'))
            ->assertOk()
            ->assertJsonCount(0, 'notifications');

        $this->actingAs($admin)
            ->getJson(route('rating-notifications.recent'))
            ->assertOk()
            ->assertJsonCount(0, 'notifications');

        $this->actingAs($otherManager)
            ->getJson(route('rating-notifications.recent'))
            ->assertOk()
            ->assertJsonCount(0, 'notifications');
    }

    public function test_rating_feed_recent_is_scoped_and_unique_by_employee(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        $branch = Branch::create(['name' => 'Chi nhanh A']);
        $otherBranch = Branch::create(['name' => 'Chi nhanh B']);
        [$employeeA, $tokenA] = $this->employeeWithRatingQr($branch);
        [$employeeB, $tokenB] = $this->employeeWithRatingQr($branch);
        [$employeeC, $tokenC] = $this->employeeWithRatingQr($branch);
        [$employeeD, $tokenD] = $this->employeeWithRatingQr($branch);
        [$otherEmployee, $otherToken] = $this->employeeWithRatingQr($otherBranch);
        $viewer = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $otherViewer = User::factory()->create(['role' => 'staff', 'branch_id' => $otherBranch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);

        foreach ([$employeeA, $employeeB, $employeeC, $employeeD, $otherEmployee] as $employee) {
            $this->activeNormalAttendance($employee, $now);
        }

        $this->submit($tokenA, 'guest-a-old', 'average', 'Cu A', $now);
        $this->submit($tokenB, 'guest-b', 'bad', 'Cu B', $now->copy()->addMinute());
        $this->submit($tokenA, 'guest-a-new', 'good', 'Moi A', $now->copy()->addMinutes(2));
        $this->submit($tokenC, 'guest-c', 'good', 'Cu C', $now->copy()->addMinutes(3));
        $this->submit($tokenD, 'guest-d', 'good', 'Cu D', $now->copy()->addMinutes(4));
        $this->submit($otherToken, 'guest-other', 'bad', 'Khac chi nhanh', $now->copy()->addMinutes(5));

        $response = $this->actingAs($viewer)
            ->getJson(route('ratings.feed.recent'))
            ->assertOk()
            ->assertJsonCount(3, 'ratings')
            ->json('ratings');

        $this->assertSame(3, count(array_unique(array_column($response, 'employee_name'))));
        $this->assertNotContains($otherEmployee->name, array_column($response, 'employee_name'));
        $this->assertNotContains('Cu A', array_column($response, 'comment'));

        $this->actingAs($manager)
            ->getJson(route('ratings.feed.recent'))
            ->assertOk()
            ->assertJsonCount(3, 'ratings');

        $otherResponse = $this->actingAs($otherViewer)
            ->getJson(route('ratings.feed.recent'))
            ->assertOk()
            ->json('ratings');

        $this->assertSame([$otherEmployee->name], array_column($otherResponse, 'employee_name'));
    }

    public function test_rating_feed_index_filters_scope_and_hides_audit_data(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        $branch = Branch::create(['name' => 'Chi nhanh A']);
        $otherBranch = Branch::create(['name' => 'Chi nhanh B']);
        [$employee, $token] = $this->employeeWithRatingQr($branch);
        [$otherEmployee, $otherToken] = $this->employeeWithRatingQr($otherBranch);
        $viewer = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $this->activeNormalAttendance($employee, $now);
        $this->activeNormalAttendance($otherEmployee, $now);

        $this->submit($token, 'guest-feed', 'bad', 'Nhanh hon nua', $now);
        $this->submit($otherToken, 'guest-feed-other', 'good', 'Khong duoc thay', $now->copy()->addMinute());

        $this->actingAs($viewer)
            ->get(route('ratings.feed.index', ['branch_id' => $otherBranch->id, 'rating' => 'bad']))
            ->assertOk()
            ->assertSee('Nhanh hon nua')
            ->assertDontSee('Khong duoc thay')
            ->assertDontSee('guest_browser_hash')
            ->assertDontSee('ip_hash')
            ->assertDontSee('user_agent_hash')
            ->assertDontSee('browser token');
    }

    public function test_mark_read_is_scoped_to_notification_owner(): void
    {
        $now = Carbon::parse('2026-06-25 10:00:00');
        Carbon::setTestNow($now);
        [$employee, $rawToken] = $this->employeeWithRatingQr();
        $this->activeNormalAttendance($employee, $now);
        $otherStaff = User::factory()->create(['role' => 'staff', 'branch_id' => $employee->branch_id]);

        $this->submit($rawToken, 'guest-read', 'good', null, $now);
        $notification = $employee->notifications()->firstOrFail();

        $this->actingAs($otherStaff)
            ->patchJson(route('rating-notifications.read', $notification))
            ->assertForbidden();

        $this->actingAs($employee)
            ->patchJson(route('rating-notifications.read', $notification))
            ->assertOk()
            ->assertJsonPath('read', true);

        $this->actingAs($employee)
            ->getJson(route('rating-notifications.recent'))
            ->assertOk()
            ->assertJsonCount(0, 'notifications');
    }

    public function test_popup_assets_include_countdown_progress_and_safe_font_stack(): void
    {
        $js = file_get_contents(resource_path('js/rating-notifications.js'));
        $css = file_get_contents(resource_path('css/rating-notifications.css'));

        $this->assertStringContainsString('rating-toast__progress', $js);
        $this->assertStringContainsString('toastTimers.set(notification.id', $js);
        $this->assertStringContainsString('window.clearTimeout(timer)', $js);
        $this->assertStringContainsString('.rating-toast__progress', $css);
        $this->assertStringContainsString('animation: rating-toast-countdown 5s linear forwards', $css);
        $this->assertStringContainsString('@keyframes rating-toast-countdown', $css);
        $this->assertStringContainsString('font-family: "Inter", "Roboto", "Times New Roman", serif', $css);
    }

    private function submit(string $rawToken, string $guestToken, string $rating, ?string $comment, Carbon $now): void
    {
        app(RatingSubmissionService::class)->submit(
            qrToken: $rawToken,
            guestBrowserToken: str_pad($guestToken, 64, '-'),
            rating: $rating,
            comment: $comment,
            now: $now,
            ipAddress: '127.0.0.1',
            userAgent: 'Feature test'
        );
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
}
