<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\DataTransferObjects\SentinelConfig\ProviderConfig;
use App\Enums\AI\AiProvider;
use App\Exceptions\NoProviderKeyException;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\Context\ContextBag;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\Support\PrismReviewFallbackLoop;
use App\Services\Reviews\Support\PrismReviewPromptPreparer;
use App\Services\Reviews\Support\PrismReviewPromptSnapshotFactory;
use App\Services\Reviews\Support\PrismReviewProviderResolver;
use App\Services\Reviews\Support\PrismReviewResponseFactory;
use App\Services\Reviews\Support\PrismStructuredReviewClient;
use App\Services\Reviews\ValueObjects\PullRequestMetrics;
use App\Services\Reviews\ValueObjects\ReviewResult;

/**
 * AI-powered review engine using PrismPHP for LLM integration.
 *
 * Uses BYOK (Bring Your Own Key) provider keys from repository configuration.
 * System keys are NOT used for customer reviews - BYOK is mandatory.
 */
final readonly class PrismReviewEngine implements ReviewEngine
{
    /**
     * Create a new engine instance.
     */
    public function __construct(
        private PrismReviewProviderResolver $providerResolver,
        private PrismReviewResponseFactory $responseFactory,
        private ModelLimitsResolver $modelLimitsResolver,
        private PrismReviewPromptPreparer $promptPreparer,
        private PrismStructuredReviewClient $structuredReviewClient,
        private PrismReviewPromptSnapshotFactory $promptSnapshotFactory,
        private PrismReviewFallbackLoop $fallbackLoop,
    ) {}

    /**
     * Perform AI-powered code review using ContextBag.
     *
     * Uses BYOK provider keys from repository configuration.
     * Supports provider preferences and fallback retry logic.
     *
     * @param  array{repository: Repository, policy_snapshot: array<string, mixed>, context_bag: ContextBag}  $context
     *
     * @throws NoProviderKeyException When no BYOK provider keys are configured for the repository
     */
    public function review(array $context): ReviewResult
    {
        /** @var Repository $repository */
        $repository = $context['repository'];

        /** @var array<string, mixed> $policySnapshot */
        $policySnapshot = $context['policy_snapshot'];

        $providerConfig = $this->providerResolver->resolveProviderConfig($policySnapshot);
        $providersToTry = $this->providerResolver->getProvidersToTry($repository, $providerConfig);

        if ($providersToTry === []) {
            throw NoProviderKeyException::noProvidersConfigured();
        }

        return $this->fallbackLoop->execute(
            $providersToTry,
            $providerConfig,
            fn (AiProvider $aiProvider): ReviewResult => $this->executeReview($context, $aiProvider, $providerConfig),
        );
    }

    /**
     * Execute a review with a specific provider.
     *
     * @param  array{repository: Repository, policy_snapshot: array<string, mixed>, context_bag: ContextBag}  $context
     */
    private function executeReview(array $context, AiProvider $aiProvider, ProviderConfig $providerConfig): ReviewResult
    {
        $startTime = microtime(true);

        /** @var Repository $repository */
        $repository = $context['repository'];

        $providerKey = $this->providerResolver->getProviderKey($repository, $aiProvider);

        if (! $providerKey instanceof ProviderKey) {
            throw NoProviderKeyException::forProvider($aiProvider->value);
        }

        $apiKey = $providerKey->encrypted_key;
        $provider = $this->providerResolver->mapToProvider($aiProvider);
        $model = $this->providerResolver->resolveModel($aiProvider, $providerConfig, $providerKey);

        $bag = $context['context_bag'];
        $limits = $this->modelLimitsResolver->resolve($aiProvider, $model);
        $preparedPrompts = $this->promptPreparer->prepare(
            $bag,
            $context['policy_snapshot'],
            $aiProvider,
            $model,
            $limits,
            $apiKey,
        );
        $systemPrompt = $preparedPrompts['system_prompt'];
        $userPrompt = $preparedPrompts['user_prompt'];
        $outputBudget = $preparedPrompts['output_budget'];

        $promptSnapshot = $this->promptSnapshotFactory->make($systemPrompt, $userPrompt);
        $inputMetrics = $bag->metrics;

        $enableThinking = $aiProvider === AiProvider::Anthropic
            && config('prism.providers.anthropic.default_thinking_budget', 2048) > 0;
        $response = $this->structuredReviewClient->execute(
            $provider->value,
            $model,
            $apiKey,
            $this->responseFactory->buildReviewSchema(),
            $systemPrompt,
            $userPrompt,
            $outputBudget,
            $enableThinking,
        );

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        $inputMetricsVO = new PullRequestMetrics(
            filesChanged: $inputMetrics['files_changed'] ?? 0,
            linesAdded: $inputMetrics['lines_added'] ?? 0,
            linesDeleted: $inputMetrics['lines_deleted'] ?? 0,
        );

        return $this->responseFactory->parseStructuredResponse(
            $response,
            $inputMetricsVO,
            $model,
            $provider->value,
            $durationMs,
            $promptSnapshot,
        );
    }
}
