<?php

namespace App\Services;

use App\Models\CustomerRating;
use App\Notifications\CustomerRatingReceivedNotification;

class CustomerRatingNotificationService
{
    public function notifyRatingCreated(CustomerRating $rating): void
    {
        $rating->loadMissing(['employee.branch', 'reward']);

        $rating->employee?->notify(new CustomerRatingReceivedNotification($rating));
    }
}
