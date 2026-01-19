<?php

declare(strict_types=1);

namespace App\Providers;

use App\Boost\Windsurf;
use Illuminate\Support\ServiceProvider;
use Laravel\Boost\Boost;

final class WindsurfBoostServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (! app()->environment(['production', 'development'])) {
            Boost::registerCodeEnvironment('windsurf', Windsurf::class);
        }
    }
}
