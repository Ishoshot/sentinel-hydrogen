<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Actions\GitHub\PostPullRequestGreeting;
use App\Services\Context\Collectors\DiffCollector;
use App\Services\Context\Collectors\LinkedIssueCollector;
use App\Services\Context\Collectors\PullRequestCommentCollector;
use App\Services\Context\Collectors\RepositoryContextCollector;
use App\Services\Context\Collectors\ReviewHistoryCollector;
use App\Services\Context\ContextEngine;
use App\Services\Context\Contracts\ContextEngineContract;
use App\Services\Context\Filters\BinaryFileFilter;
use App\Services\Context\Filters\RelevanceFilter;
use App\Services\Context\Filters\SensitiveDataFilter;
use App\Services\Context\Filters\TokenLimitFilter;
use App\Services\Context\Filters\VendorPathFilter;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\DefaultReviewEngine;
use App\Services\Reviews\PrismReviewEngine;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PostsGreetingComment::class, PostPullRequestGreeting::class);

        // Register the Context Engine with collectors and filters
        $this->app->singleton(ContextEngine::class, function (): ContextEngine {
            $engine = new ContextEngine();

            // Register collectors (highest priority runs first)
            $engine->registerCollector(app(DiffCollector::class));              // Priority 100
            $engine->registerCollector(app(LinkedIssueCollector::class));       // Priority 80
            $engine->registerCollector(app(PullRequestCommentCollector::class)); // Priority 70
            $engine->registerCollector(app(ReviewHistoryCollector::class));     // Priority 60
            $engine->registerCollector(app(RepositoryContextCollector::class)); // Priority 50

            // Register filters (lowest order runs first)
            $engine->registerFilter(app(VendorPathFilter::class));   // Order 10
            $engine->registerFilter(app(BinaryFileFilter::class));   // Order 20
            $engine->registerFilter(app(SensitiveDataFilter::class)); // Order 30
            $engine->registerFilter(app(RelevanceFilter::class));    // Order 40
            $engine->registerFilter(app(TokenLimitFilter::class));   // Order 100

            return $engine;
        });

        $this->app->bind(ContextEngineContract::class, ContextEngine::class);

        $this->app->bind(ReviewEngine::class, function (): ReviewEngine {
            $anthropicKey = config('prism.providers.anthropic.api_key', '');
            $openAiKey = config('prism.providers.openai.api_key', '');

            if ($anthropicKey !== '' || $openAiKey !== '') {
                return app(PrismReviewEngine::class);
            }

            return app(DefaultReviewEngine::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
