<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Context\Collectors\DiffCollector;
use App\Services\Context\Collectors\FileContextCollector;
use App\Services\Context\Collectors\GuidelinesCollector;
use App\Services\Context\Collectors\ImpactAnalysisCollector;
use App\Services\Context\Collectors\LinkedIssueCollector;
use App\Services\Context\Collectors\ProjectContextCollector;
use App\Services\Context\Collectors\PullRequestCommentCollector;
use App\Services\Context\Collectors\RepositoryContextCollector;
use App\Services\Context\Collectors\ReviewHistoryCollector;
use App\Services\Context\Collectors\SemanticCollector;
use App\Services\Context\ContextEngine;
use App\Services\Context\Contracts\ContextEngineContract;
use App\Services\Context\Contracts\TokenCounter;
use App\Services\Context\Filters\BinaryFileFilter;
use App\Services\Context\Filters\ConfiguredPathFilter;
use App\Services\Context\Filters\RelevanceFilter;
use App\Services\Context\Filters\SensitiveDataFilter;
use App\Services\Context\Filters\TokenLimitFilter;
use App\Services\Context\Filters\VendorPathFilter;
use App\Services\Context\TokenCounting\AnthropicTokenCounter;
use App\Services\Context\TokenCounting\CompositeTokenCounter;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;
use App\Services\Context\TokenCounting\OpenAiTokenCounter;
use App\Services\Contracts\SentinelMessageServiceContract;
use App\Services\Reviews\Contracts\ModelLimitsResolverContract;
use App\Services\Reviews\Contracts\ProviderKeyResolver;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\Contracts\ReviewPolicyResolverContract;
use App\Services\Reviews\ModelLimitsResolver;
use App\Services\Reviews\PrismReviewEngine;
use App\Services\Reviews\ProviderKeyResolverService;
use App\Services\Reviews\ReviewPolicyResolver;
use App\Services\Semantic\Contracts\SemanticAnalyzerInterface;
use App\Services\Semantic\SemanticAnalyzerService;
use App\Services\SentinelMessageService;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Service provider for code review services.
 *
 * Registers the review engine, context engine with collectors/filters,
 * token counting, and semantic analysis services.
 */
final class ReviewServiceProvider extends ServiceProvider
{
    /**
     * Register review services.
     */
    #[Override]
    public function register(): void
    {
        $this->registerTokenCounter();
        $this->registerContextEngine();
        $this->registerReviewEngine();
        $this->registerSemanticAnalyzer();
    }

    /**
     * Register the composite token counter.
     */
    private function registerTokenCounter(): void
    {
        $this->app->singleton(TokenCounter::class, fn (): TokenCounter => new CompositeTokenCounter(
            app(OpenAiTokenCounter::class),
            app(AnthropicTokenCounter::class),
            app(HeuristicTokenCounter::class),
        ));
    }

    /**
     * Register the context engine with collectors and filters.
     */
    private function registerContextEngine(): void
    {
        $this->app->singleton(ContextEngine::class, function (): ContextEngine {
            $engine = new ContextEngine;

            $this->registerCollectors($engine);
            $this->registerFilters($engine);

            return $engine;
        });

        $this->app->bind(ContextEngineContract::class, ContextEngine::class);
    }

    /**
     * Register context collectors on the engine.
     *
     * Collectors gather context data. Higher priority runs first.
     */
    private function registerCollectors(ContextEngine $engine): void
    {
        $engine->registerCollector(app(DiffCollector::class));               // Priority 100
        $engine->registerCollector(app(FileContextCollector::class));        // Priority 85
        $engine->registerCollector(app(SemanticCollector::class));           // Priority 80
        $engine->registerCollector(app(LinkedIssueCollector::class));        // Priority 80
        $engine->registerCollector(app(ImpactAnalysisCollector::class));     // Priority 75
        $engine->registerCollector(app(PullRequestCommentCollector::class)); // Priority 70
        $engine->registerCollector(app(ReviewHistoryCollector::class));      // Priority 60
        $engine->registerCollector(app(ProjectContextCollector::class));     // Priority 55
        $engine->registerCollector(app(RepositoryContextCollector::class));  // Priority 50
        $engine->registerCollector(app(GuidelinesCollector::class));         // Priority 45
    }

    /**
     * Register context filters on the engine.
     *
     * Filters process/reduce context. Lower order runs first.
     */
    private function registerFilters(ContextEngine $engine): void
    {
        $engine->registerFilter(app(VendorPathFilter::class));      // Order 10
        $engine->registerFilter(app(ConfiguredPathFilter::class));  // Order 15
        $engine->registerFilter(app(BinaryFileFilter::class));      // Order 20
        $engine->registerFilter(app(SensitiveDataFilter::class));   // Order 30
        $engine->registerFilter(app(RelevanceFilter::class));       // Order 40
        $engine->registerFilter(app(TokenLimitFilter::class));      // Order 100
    }

    /**
     * Register the review engine and provider key resolver.
     */
    private function registerReviewEngine(): void
    {
        // BYOK Provider Key Resolver (scoped per request for caching)
        $this->app->scoped(ProviderKeyResolver::class, ProviderKeyResolverService::class);

        // Review Engine - uses BYOK keys, no fallback to system keys
        $this->app->bind(ReviewEngine::class, PrismReviewEngine::class);

        // Message service for PR comments
        $this->app->bind(SentinelMessageServiceContract::class, SentinelMessageService::class);

        // Policy and limits resolvers
        $this->app->bind(ReviewPolicyResolverContract::class, ReviewPolicyResolver::class);
        $this->app->bind(ModelLimitsResolverContract::class, ModelLimitsResolver::class);
    }

    /**
     * Register the semantic analyzer service.
     */
    private function registerSemanticAnalyzer(): void
    {
        $this->app->bind(SemanticAnalyzerInterface::class, SemanticAnalyzerService::class);
    }
}
