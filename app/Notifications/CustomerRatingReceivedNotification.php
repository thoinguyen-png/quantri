<?php

namespace App\Notifications;

use App\Models\CustomerRating;
use App\Models\CustomerRatingReward;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CustomerRatingReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CustomerRating $rating)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->rating->loadMissing(['employee.branch', 'reward']);

        return [
            'customer_rating_id' => $this->rating->id,
            'employee_id' => $this->rating->employee_id,
            'employee_name' => $this->rating->employee?->name,
            'branch_id' => $this->rating->branch_id,
            'branch_name' => $this->rating->employee?->branch?->name,
            'rating' => $this->rating->rating,
            'comment' => $this->rating->comment,
            'submitted_at' => $this->rating->submitted_at?->toISOString(),
            'reward_status' => $this->rating->reward?->status,
            'reward_reason' => $this->rating->reward?->reason_code,
            'priority' => $this->rating->rating === CustomerRating::RATING_BAD ? 'high' : 'normal',
        ];
    }

    public static function rewardStatusLabel(?string $status, ?string $reason = null): string
    {
        if ($status === null) {
            return 'Không áp dụng';
        }

        if ($status === CustomerRatingReward::STATUS_ELIGIBLE) {
            return 'Đủ điều kiện thưởng';
        }

        if ($status === CustomerRatingReward::STATUS_PENDING) {
            return 'Chờ kiểm tra';
        }

        if ($status === CustomerRatingReward::STATUS_REJECTED && $reason === 'daily_cap_reached') {
            return 'Vượt trần thưởng';
        }

        return match ($status) {
            CustomerRatingReward::STATUS_REJECTED => 'Không thưởng',
            CustomerRatingReward::STATUS_RISK_REVIEW => 'Cần rà soát',
            CustomerRatingReward::STATUS_PAID => 'Đã thanh toán',
            default => 'Không áp dụng',
        };
    }
}
