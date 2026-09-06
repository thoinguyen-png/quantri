<?php

namespace App\Policies;

use App\Models\User;

class RatingQrTokenPolicy
{
    public function view(User $viewer, User $target): bool
    {
        if ($target->role === 'admin') {
            return false;
        }

        if ($viewer->role === 'admin') {
            return in_array($target->role, ['staff', 'cashier', 'manager'], true);
        }

        if ((int) $viewer->id === (int) $target->id) {
            return in_array($viewer->role, ['staff', 'cashier', 'manager'], true);
        }

        return $viewer->role === 'manager'
            && in_array($target->role, ['staff', 'cashier'], true)
            && (int) $viewer->branch_id === (int) $target->branch_id;
    }

    public function manage(User $viewer, User $target): bool
    {
        return $viewer->role === 'admin'
            && in_array($target->role, ['staff', 'cashier', 'manager'], true);
    }
}
