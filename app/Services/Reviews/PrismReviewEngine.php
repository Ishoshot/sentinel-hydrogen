<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\AI\AiProvider;
use App\Exceptions\NoProviderKeyException;
use App\Models\Repository;
use App\Services\Context\ContextBag;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\Support\PrismProviderReviewExecutor;
use App\Services\Reviews\Support\PrismReviewFallbackLoop;
use App\Services\Reviews\Support\PrismReviewProviderResolver;
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
        private PrismProviderReviewExecutor $providerReviewExecutor,
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
            fn (AiProvider $aiProvider): ReviewResult => $this->providerReviewExecutor->execute($context, $aiProvider, $providerConfig),
        );
    }
}
