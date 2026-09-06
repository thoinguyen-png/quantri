<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StaffCardPreviewService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaffCardPreviewController extends Controller
{
    public function show(User $user, StaffCardPreviewService $cards): View
    {
        Gate::authorize('viewStaffCard', $user);

        return view('staff_cards.preview', [
            'card' => $cards->dataFor($user),
        ]);
    }
}
