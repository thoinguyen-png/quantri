<?php

namespace App\Policies;

use App\Models\User;

class StaffCardPolicy
{
    public function view(User $viewer, User $target): bool
    {
        if (! in_array($target->role, ['staff', 'cashier', 'manager'], true)) {
            return false;
        }

        if ($viewer->role === 'admin') {
            return true;
        }

        return $viewer->role === 'manager'
            && in_array($target->role, ['staff', 'cashier'], true)
            && (int) $viewer->branch_id === (int) $target->branch_id;
    }
}
