<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\QrToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceSessionConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_sessions_are_blocked_against_stale_concurrent_writes(): void
    {
        $sessionId = str_repeat('a', 40);
        $initial = $this->databaseSession($sessionId);
        $initial->start();
        $initial->put('existing_key', 'value');
        $initial->save();

        // This request starts first and therefore holds an old in-memory payload.
        $staleBackgroundRequest = $this->databaseSession($sessionId);
        $staleBackgroundRequest->start();

        // The QR request finishes and persists the attendance-flow context.
        $qrRequest = $this->databaseSession($sessionId);
        $qrRequest->start();
        $qrRequest->put('qr_token', 'safe-test-token');
        $qrRequest->put('qr_branch_id', 1);
        $qrRequest->put('qr_scanned_at', now()->toIso8601String());
        $qrRequest->save();

        // DatabaseSessionHandler updates the complete payload, so the stale
        // request would erase the QR keys if middleware allowed this overlap.
        $staleBackgroundRequest->put('background_result', 'done');
        $staleBackgroundRequest->save();

        $overwritten = $this->databaseSession($sessionId);
        $overwritten->start();

        $this->assertFalse($overwritten->has('qr_token'));
        $this->assertTrue(config('session.block'));
        $this->assertSame(config('cache.default'), config('session.block_store'));
    }

    public function test_face_verification_keeps_the_existing_qr_session_context(): void
    {
        [$user, $branch] = $this->attendanceUser();
        $scannedAt = now()->toIso8601String();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'qr_token' => 'qr-context-kept',
                'qr_branch_id' => $branch->id,
                'qr_scanned_at' => $scannedAt,
            ])
            ->postJson(route('face.verify-pass'), [
                'distance' => 0.1,
            ]);

        $response
            ->assertOk()
            ->assertSessionHas('qr_token', 'qr-context-kept')
            ->assertSessionHas('qr_branch_id', $branch->id)
            ->assertSessionHas('qr_scanned_at', $scannedAt)
            ->assertSessionHas('face_verified_user_id', $user->id)
            ->assertSessionHas('face_verified_distance', 0.1);
    }

    public function test_expired_qr_context_returns_to_scanner_without_creating_attendance(): void
    {
        [$user, $branch] = $this->attendanceUser();
        $token = 'expired-session-qr';

        QrToken::create([
            'token' => $token,
            'branch_id' => $branch->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'qr_token' => $token,
                'qr_branch_id' => $branch->id,
                'qr_scanned_at' => now()->subSeconds(181),
                'face_verified_user_id' => $user->id,
                'face_verified_at' => now(),
                'face_verified_distance' => 0.1,
            ])
            ->post(route('attendance.store'), [
                'latitude' => 10.0,
                'longitude' => 106.0,
                'face_verified' => '1',
            ]);

        $response
            ->assertRedirect(route('attendance.scanner'))
            ->assertSessionHasErrors('qr');

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_qr_token_may_expire_after_scan_while_server_session_finishes_flow(): void
    {
        [$user, $branch] = $this->attendanceUser();
        $token = 'short-lived-display-token';

        $this->travelTo(Carbon::parse('2026-07-25 08:00:00'));

        QrToken::create([
            'token' => $token,
            'branch_id' => $branch->id,
            'expires_at' => now()->addSeconds(12),
        ]);

        $this
            ->actingAs($user)
            ->get(route('attendance.scan', ['token' => $token]))
            ->assertRedirect(route('attendance.checkin'))
            ->assertSessionHas('qr_token', $token)
            ->assertSessionHas('qr_branch_id', $branch->id);

        $this->travel(13)->seconds();

        $this
            ->actingAs($user)
            ->postJson(route('face.verify-pass'), [
                'distance' => 0.1,
            ])
            ->assertOk();

        $this
            ->actingAs($user)
            ->post(route('attendance.store'), [
                'latitude' => 10.0,
                'longitude' => 106.0,
                'face_verified' => '1',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('attendance_modal');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'status' => 'checked_in',
        ]);
    }

    public function test_gps_uses_nearest_branch_instead_of_qr_or_user_assigned_branch(): void
    {
        [$user, $nearestBranch] = $this->attendanceUser();

        $assignedQrBranch = Branch::create([
            'name' => 'Assigned But Far Branch',
            'latitude' => 11.0,
            'longitude' => 107.0,
            'gps_radius' => 20,
        ]);

        $user->update([
            'branch_id' => $assignedQrBranch->id,
        ]);

        $token = 'qr-from-assigned-far-branch';

        QrToken::create([
            'token' => $token,
            'branch_id' => $assignedQrBranch->id,
            'expires_at' => now()->addSeconds(12),
        ]);

        $this
            ->actingAs($user)
            ->get(route('attendance.scan', ['token' => $token]))
            ->assertRedirect(route('attendance.checkin'))
            ->assertSessionHas('qr_branch_id', $assignedQrBranch->id);

        $this
            ->actingAs($user)
            ->postJson(route('face.verify-pass'), [
                'distance' => 0.1,
            ])
            ->assertOk();

        $this
            ->actingAs($user)
            ->post(route('attendance.store'), [
                'latitude' => (float) $nearestBranch->latitude,
                'longitude' => (float) $nearestBranch->longitude,
                'face_verified' => '1',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('attendance_modal');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'status' => 'checked_in',
        ]);
    }

    public function test_checkin_page_contains_only_one_programmatic_attendance_submit(): void
    {
        [$user] = $this->attendanceUser();

        $html = $this
            ->actingAs($user)
            ->get(route('attendance.checkin'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, "document.getElementById('attendance-form').submit();")
        );
    }

    public function test_gps_prioritizes_qr_branch_when_within_allowed_radius(): void
    {
        [$user, $primaryBranch] = $this->attendanceUser();

        // Tạo branch liền kề cách primaryBranch chỉ vài mét
        $adjacentBranch = Branch::create([
            'name' => 'Adjacent Branch',
            'latitude' => 10.0001,
            'longitude' => 106.0001,
            'gps_radius' => 100,
        ]);

        $token = 'qr-from-primary-branch';

        QrToken::create([
            'token' => $token,
            'branch_id' => $primaryBranch->id,
            'expires_at' => now()->addSeconds(12),
        ]);

        $this
            ->actingAs($user)
            ->get(route('attendance.scan', ['token' => $token]))
            ->assertRedirect(route('attendance.checkin'))
            ->assertSessionHas('qr_branch_id', $primaryBranch->id);

        $this
            ->actingAs($user)
            ->postJson(route('face.verify-pass'), [
                'distance' => 0.1,
            ])
            ->assertOk();

        // Tọa độ gần adjacentBranch hơn một chút nhưng vẫn nằm trong bán kính của primaryBranch
        $this
            ->actingAs($user)
            ->post(route('attendance.store'), [
                'latitude' => 10.00008,
                'longitude' => 106.00008,
                'face_verified' => '1',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('attendance_modal');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'status' => 'checked_in',
        ]);
    }

    private function databaseSession(string $sessionId): Store
    {
        $handler = new DatabaseSessionHandler(
            DB::connection(),
            'sessions',
            120,
        );

        return new Store('attendance-session-test', $handler, $sessionId);
    }

    private function attendanceUser(): array
    {
        $branch = Branch::create([
            'name' => 'Attendance Session Branch',
            'latitude' => 10.0,
            'longitude' => 106.0,
            'gps_radius' => 100,
        ]);

        $user = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'face_descriptor' => json_encode(array_fill(0, 128, 0.1)),
            'face_verification_mode' => 'normal',
            'status' => 'chinh_thuc',
        ]);

        return [$user, $branch];
    }
}
