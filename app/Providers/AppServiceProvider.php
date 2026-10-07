<?php

namespace App\Providers;

use App\Models\Report;
use App\Models\ServiceAreaRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        Schema::defaultStringLength(191);

        View::composer('layouts.admin', function ($view) {
            $view->with(
                'pendingReportCount',
                Report::query()->where('status', 'pending')->count()
            )->with(
                'pendingServiceAreaRequestCount',
                ServiceAreaRequest::query()->where('status', 'pending')->count()
            );
        });
    }
}
