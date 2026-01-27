<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Override;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    #[Override]
    public function boot(): void
    {
        parent::boot();

        Horizon::night();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    #[Override]
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            $expectedToken = config('horizon.access_token');

            if ($expectedToken === null || $expectedToken === '') {
                return false;
            }

            $queryToken = request()->query('token');
            if ($queryToken === $expectedToken) {
                session(['horizon_authenticated' => true]);

                return true;
            }

            return session('horizon_authenticated') === true;
        });
    }
}
