<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\GitHub\Contracts\PostsAutoReviewDisabledComment;
use App\Actions\GitHub\Contracts\PostsConfigErrorComment;
use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Actions\GitHub\PostAutoReviewDisabledComment;
use App\Actions\GitHub\PostConfigErrorComment;
use App\Actions\GitHub\PostPullRequestGreeting;
use App\Actions\GitHub\PostSkipReasonComment;
use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Actions\SentinelConfig\FetchSentinelConfig;
use App\Services\Context\Collectors\DiffCollector;
use App\Services\Context\Collectors\FileContextCollector;
use App\Services\Context\Collectors\GuidelinesCollector;
use App\Services\Context\Collectors\LinkedIssueCollector;
use App\Services\Context\Collectors\PullRequestCommentCollector;
use App\Services\Context\Collectors\RepositoryContextCollector;
use App\Services\Context\Collectors\ReviewHistoryCollector;
use App\Services\Context\Collectors\SemanticCollector;
use App\Services\Context\ContextEngine;
use App\Services\Context\Contracts\ContextEngineContract;
use App\Services\Context\Filters\BinaryFileFilter;
use App\Services\Context\Filters\ConfiguredPathFilter;
use App\Services\Context\Filters\RelevanceFilter;
use App\Services\Context\Filters\SensitiveDataFilter;
use App\Services\Context\Filters\TokenLimitFilter;
use App\Services\Context\Filters\VendorPathFilter;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use App\Services\GitHub\GitHubApiService;
use App\Services\GitHub\GitHubAppService;
use App\Services\Reviews\Contracts\ProviderKeyResolver;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\PrismReviewEngine;
use App\Services\Reviews\ProviderKeyResolverService;
use App\Services\SentinelConfig\Contracts\SentinelConfigParser;
use App\Services\SentinelConfig\SentinelConfigParserService;
use Illuminate\Support\ServiceProvider;
use Override;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(PostsGreetingComment::class, PostPullRequestGreeting::class);
        $this->app->bind(PostsConfigErrorComment::class, PostConfigErrorComment::class);
        $this->app->bind(PostsAutoReviewDisabledComment::class, PostAutoReviewDisabledComment::class);
        $this->app->bind(PostsSkipReasonComment::class, PostSkipReasonComment::class);

        // Register GitHub API service contract
        $this->app->bind(GitHubApiServiceContract::class, GitHubApiService::class);
        $this->app->bind(GitHubAppServiceContract::class, GitHubAppService::class);

        // Register SentinelConfig action contracts
        $this->app->bind(FetchesSentinelConfig::class, FetchSentinelConfig::class);

        // Register the Context Engine with collectors and filters
        $this->app->singleton(ContextEngine::class, function (): ContextEngine {
            $engine = new ContextEngine();

            // Register collectors (highest priority runs first)
            $engine->registerCollector(app(DiffCollector::class));              // Priority 100
            $engine->registerCollector(app(FileContextCollector::class));       // Priority 85
            $engine->registerCollector(app(SemanticCollector::class));          // Priority 80
            $engine->registerCollector(app(LinkedIssueCollector::class));       // Priority 80
            $engine->registerCollector(app(PullRequestCommentCollector::class)); // Priority 70
            $engine->registerCollector(app(ReviewHistoryCollector::class));     // Priority 60
            $engine->registerCollector(app(RepositoryContextCollector::class)); // Priority 50
            $engine->registerCollector(app(GuidelinesCollector::class));        // Priority 45

            // Register filters (lowest order runs first)
            $engine->registerFilter(app(VendorPathFilter::class));      // Order 10
            $engine->registerFilter(app(ConfiguredPathFilter::class));  // Order 15
            $engine->registerFilter(app(BinaryFileFilter::class));      // Order 20
            $engine->registerFilter(app(SensitiveDataFilter::class));   // Order 30
            $engine->registerFilter(app(RelevanceFilter::class));       // Order 40
            $engine->registerFilter(app(TokenLimitFilter::class));      // Order 100

            return $engine;
        });

        $this->app->bind(ContextEngineContract::class, ContextEngine::class);

        // Register BYOK Provider Key Resolver (scoped per request for caching)
        $this->app->scoped(ProviderKeyResolver::class, ProviderKeyResolverService::class);

        // Register Review Engine - uses BYOK keys, no fallback to system keys
        $this->app->bind(ReviewEngine::class, PrismReviewEngine::class);

        // Register Sentinel Config Parser
        $this->app->bind(SentinelConfigParser::class, SentinelConfigParserService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
