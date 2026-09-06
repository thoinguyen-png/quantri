<?php

namespace App\Providers;

use App\Policies\RatingQrTokenPolicy;
use App\Policies\BranchPolicy;
use App\Policies\StaffCardPolicy;
use App\Policies\StaffCardExportPolicy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewRatingQr', [RatingQrTokenPolicy::class, 'view']);
        Gate::define('manageRatingQr', [RatingQrTokenPolicy::class, 'manage']);
        Gate::define('updateStaffCardLogo', [BranchPolicy::class, 'updateStaffCardLogo']);
        Gate::define('viewStaffCard', [StaffCardPolicy::class, 'view']);
        Gate::define('exportStaffCards', [StaffCardExportPolicy::class, 'use']);

        if (app()->environment('local')) {
            URL::forceScheme('https');
        }
    }
}
