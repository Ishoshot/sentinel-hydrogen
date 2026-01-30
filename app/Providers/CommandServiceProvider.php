<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Commands\CommandAgentService;
use App\Services\Commands\Contracts\CommandAgentServiceContract;
use App\Services\Commands\Contracts\PullRequestContextServiceContract;
use App\Services\Commands\PullRequestContextService;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Service provider for @sentinel command services.
 *
 * Registers the command agent and PR context services used for
 * interactive GitHub comment commands (@sentinel explain, etc.).
 */
final class CommandServiceProvider extends ServiceProvider
{
    /**
     * Register command services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(CommandAgentServiceContract::class, CommandAgentService::class);
        $this->app->bind(PullRequestContextServiceContract::class, PullRequestContextService::class);
    }
}
