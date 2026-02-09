<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Slack\Contracts\SlackServiceContract;
use App\Services\Slack\SlackService;
use Illuminate\Support\ServiceProvider;
use Override;

final class SlackServiceProvider extends ServiceProvider
{
    /**
     * Register Slack services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(SlackServiceContract::class, SlackService::class);
    }
}
