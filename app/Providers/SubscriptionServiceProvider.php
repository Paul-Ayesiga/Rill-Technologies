<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserHasActiveSubscription;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;

class SubscriptionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register the middleware with a name
        $this->app['router']->aliasMiddleware('subscription', EnsureUserHasActiveSubscription::class);
    }
}
