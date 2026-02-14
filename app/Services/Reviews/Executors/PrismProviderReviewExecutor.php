<?php

declare(strict_types=1);

namespace App\Services\Reviews\Executors;

use App\DataTransferObjects\SentinelConfig\ProviderConfig;
use App\Enums\AI\AiProvider;
use App\Exceptions\NoProviderKeyException;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\Context\ContextBag;
use App\Services\Reviews\Builders\PrismReviewSchemaBuilder;
use App\Services\Reviews\Factories\PrismReviewPromptSnapshotFactory;
use App\Services\Reviews\ModelLimitsResolver;
use App\Services\Reviews\Parsers\PrismReviewResponseParser;
use App\Services\Reviews\Resolvers\PrismReviewProviderResolver;
use App\Services\Reviews\Support\PrismReviewPromptPreparer;
use App\Services\Reviews\Support\PrismStructuredReviewClient;
use App\Services\Reviews\ValueObjects\PullRequestMetrics;
use App\Services\Reviews\ValueObjects\ReviewResult;

final readonly class PrismProviderReviewExecutor
{
    /**
     * Create a new provider review executor instance.
     */
    public function __construct(
        private PrismReviewProviderResolver $providerResolver,
        private PrismReviewResponseParser $responseParser,
        private PrismReviewSchemaBuilder $schemaBuilder,
        private ModelLimitsResolver $modelLimitsResolver,
        private PrismReviewPromptPreparer $promptPreparer,
        private PrismStructuredReviewClient $structuredReviewClient,
        private PrismReviewPromptSnapshotFactory $promptSnapshotFactory,
    ) {}

    /**
     * Execute a review with a specific provider.
     *
     * @param  array{repository: Repository, policy_snapshot: array<string, mixed>, context_bag: ContextBag}  $context
     */
    public function execute(array $context, AiProvider $aiProvider, ProviderConfig $providerConfig): ReviewResult
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
            $this->schemaBuilder->build(),
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

        return $this->responseParser->parseStructuredResponse(
            $response,
            $inputMetricsVO,
            $model,
            $provider->value,
            $durationMs,
            $promptSnapshot,
        );
    }
}
