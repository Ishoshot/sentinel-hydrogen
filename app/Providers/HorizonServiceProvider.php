<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Http\Request;
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
     * Register the Horizon authorization callback.
     *
     * This determines who can access Horizon in non-local environments.
     */
    #[Override]
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function (Request $request): bool {
            if (app()->environment('local')) {
                return true;
            }

            $expectedToken = config('horizon.access_token');

            if ($expectedToken === null || $expectedToken === '') {
                return false;
            }

            $queryToken = $request->query('token');
            if ($queryToken === $expectedToken) {
                session(['horizon_authenticated' => true]);

                return true;
            }

            return session('horizon_authenticated') === true;
        });
    }
}
