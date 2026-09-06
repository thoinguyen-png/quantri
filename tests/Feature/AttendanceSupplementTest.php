<?php

namespace Tests\Feature;

use App\Models\AttendanceSupplementRequest;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSupplementTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_shift_supplement_request_stores_one_checkin_checkout_pair(): void
    {
        $workDate = today()->subDay();
        $user = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create([
            'name' => 'Ca thuong',
            'start_at' => '08:00:00',
            'end_at' => '17:00:00',
        ]);

        ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
        ]);

        $response = $this->actingAs($user)->post(route('attendance-supplements.store'), [
            'user_id' => $user->id,
            'work_date' => $workDate->toDateString(),
            'requested_checkin_time' => '08:00',
            'requested_checkout_time' => '17:00',
            'reason' => 'Bo sung cong ca thuong.',
        ]);

        $response->assertRedirect(route('attendance-supplements.index', absolute: false));

        $request = AttendanceSupplementRequest::where('user_id', $user->id)->first();

        $this->assertNotNull($request);
        $this->assertNull($request->segment_payload);
        $this->assertSame('08:00:00', $request->requested_checkin_at->format('H:i:s'));
        $this->assertSame('17:00:00', $request->requested_checkout_at->format('H:i:s'));
    }

}
