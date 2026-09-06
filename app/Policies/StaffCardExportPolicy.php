<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class StaffCardExportPolicy
{
    public function use(User $viewer, Branch $branch): bool
    {
        return $viewer->role === 'admin'
            || ($viewer->role === 'manager' && (int) $viewer->branch_id === (int) $branch->id);
    }
}
