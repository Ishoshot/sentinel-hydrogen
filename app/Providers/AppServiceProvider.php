<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Promotions\Contracts\PromotionValidatorContract;
use App\Services\Promotions\PromotionValidator;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Main application service provider.
 *
 * Handles global application bootstrapping. Domain-specific bindings
 * are registered in dedicated service providers:
 *
 * @see GitHubServiceProvider - GitHub API and action contracts
 * @see ReviewServiceProvider - Review engine, context, token counting
 * @see CommandServiceProvider - @sentinel command services
 * @see BriefingServiceProvider - Briefing data and narrative services
 * @see CodeIndexingServiceProvider - Code search and embeddings
 * @see SentinelConfigServiceProvider - Config fetching and parsing
 */
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        // Promotion validation
        $this->app->bind(PromotionValidatorContract::class, PromotionValidator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register pgvector Schema helper (package auto-discovery disabled to avoid migration loading)
        \Pgvector\Laravel\Schema::register();
    }
}
