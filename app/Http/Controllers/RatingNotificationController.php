<?php

namespace App\Http\Controllers;

use App\Notifications\CustomerRatingReceivedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class RatingNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->unreadNotifications()
            ->where('type', CustomerRatingReceivedNotification::class)
            ->latest()
            ->limit(10)
            ->get()
            ->sortByDesc(fn (DatabaseNotification $notification) => ($notification->data['priority'] ?? 'normal') === 'high' ? 1 : 0)
            ->values()
            ->take(5)
            ->map(fn (DatabaseNotification $notification) => $this->payload($notification));

        return response()->json([
            'notifications' => $notifications,
            'polling_interval_ms' => 30000,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function markRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        abort_unless(
            $notification->notifiable_type === $request->user()::class
            && (int) $notification->notifiable_id === (int) $request->user()->id
            && $notification->type === CustomerRatingReceivedNotification::class,
            403
        );

        $notification->markAsRead();

        return response()->json(['read' => true])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function payload(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'customer_rating_id' => $data['customer_rating_id'] ?? null,
            'employee_name' => $data['employee_name'] ?? 'Nhân sự',
            'rating' => $data['rating'] ?? null,
            'rating_label' => $this->ratingLabel($data['rating'] ?? null),
            'comment' => $data['comment'] ?? null,
            'submitted_at' => $data['submitted_at'] ?? $notification->created_at?->toISOString(),
            'submitted_at_label' => $notification->created_at?->format('H:i d/m/Y'),
            'reward_status' => $data['reward_status'] ?? null,
            'reward_status_label' => CustomerRatingReceivedNotification::rewardStatusLabel(
                $data['reward_status'] ?? null,
                $data['reward_reason'] ?? null
            ),
            'priority' => $data['priority'] ?? 'normal',
            'read_url' => route('rating-notifications.read', $notification, absolute: false),
        ];
    }

    private function ratingLabel(?string $rating): string
    {
        return match ($rating) {
            'bad' => 'Tệ',
            'average' => 'Trung bình',
            'good' => 'Tốt',
            default => 'Đánh giá',
        };
    }
}
