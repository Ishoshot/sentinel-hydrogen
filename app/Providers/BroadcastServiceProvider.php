<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Override;

final class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Broadcasting auth route is registered in routes/api.php
        // to ensure it uses the API middleware stack with Sanctum
    }
}
