<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\DataTransferObjects\SentinelConfig\ProviderConfig;
use App\Enums\AI\AiProvider;
use App\Enums\AI\TokenCountMode;
use App\Enums\Reviews\FindingCategory;
use App\Enums\Reviews\ReviewVerdict;
use App\Enums\Reviews\RiskLevel;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Exceptions\NoProviderKeyException;
use App\Models\AiOption;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\TokenCounter;
use App\Services\Context\Filters\TokenLimitFilter;
use App\Services\Context\TokenCounting\TokenCounterContext;
use App\Services\Reviews\Contracts\ProviderKeyResolver;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\ValueObjects\ModelLimits;
use App\Services\Reviews\ValueObjects\PromptSnapshot;
use App\Services\Reviews\ValueObjects\PullRequestMetrics;
use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewMetrics;
use App\Services\Reviews\ValueObjects\ReviewResult;
use App\Services\Reviews\ValueObjects\ReviewSummary;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Structured\Response as StructuredResponse;
use Throwable;

/**
 * AI-powered review engine using PrismPHP for LLM integration.
 *
 * Uses BYOK (Bring Your Own Key) provider keys from repository configuration.
 * System keys are NOT used for customer reviews - BYOK is mandatory.
 */
final readonly class PrismReviewEngine implements ReviewEngine
{
    private const int MAX_FALLBACK_ATTEMPTS = 3;

    private const int DEFAULT_OUTPUT_TOKENS = 8192;

    private const int MIN_CONTEXT_TOKENS = 8000;

    private const int SAFETY_MARGIN_TOKENS = 500;

    /**
     * Create a new engine instance.
     */
    public function __construct(
        private ReviewPromptBuilder $promptBuilder,
        private ProviderKeyResolver $keyResolver,
        private ModelLimitsResolver $modelLimitsResolver,
        private TokenLimitFilter $tokenLimitFilter,
        private TokenCounter $tokenCounter,
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

        $providerConfig = $this->resolveProviderConfig($policySnapshot);
        $providersToTry = $this->getProvidersToTry($repository, $providerConfig);

        if ($providersToTry === []) {
            throw NoProviderKeyException::noProvidersConfigured();
        }

        $attempts = 0;
        $maxAttempts = $providerConfig->fallback ? min(count($providersToTry), self::MAX_FALLBACK_ATTEMPTS) : 1;

        /** @var Throwable|null $lastException */
        $lastException = null;

        foreach ($providersToTry as $aiProvider) {
            if ($attempts >= $maxAttempts) {
                break;
            }

            $attempts++;

            try {
                return $this->executeReview($context, $aiProvider, $providerConfig);
            } catch (NoProviderKeyException $e) {
                $lastException = $e;
                Log::warning('Provider key not available, trying fallback', [
                    'provider' => $aiProvider->value,
                    'attempt' => $attempts,
                    'fallback_enabled' => $providerConfig->fallback,
                ]);

                if (! $providerConfig->fallback) {
                    throw $e;
                }
            } catch (Throwable $e) {
                $lastException = $e;
                Log::warning('Provider failed, trying fallback', [
                    'provider' => $aiProvider->value,
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                    'fallback_enabled' => $providerConfig->fallback,
                ]);

                if (! $providerConfig->fallback) {
                    throw $e;
                }
            }
        }

        // All attempts failed - throw the last exception or a default one
        if ($lastException !== null) {
            throw $lastException;
        }

        throw NoProviderKeyException::noProvidersConfigured();
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

        $providerKey = $this->keyResolver->getProviderKey($repository, $aiProvider);

        if (! $providerKey instanceof ProviderKey) {
            throw NoProviderKeyException::forProvider($aiProvider->value);
        }

        $apiKey = $providerKey->encrypted_key;
        $provider = $this->mapToProvider($aiProvider);
        $model = $this->resolveModel($aiProvider, $providerConfig, $providerKey);

        $bag = $context['context_bag'];
        $bag->metadata['token_counter_provider'] = $aiProvider->value;
        $bag->metadata['token_counter_model'] = $model;

        $tokenCounterContext = new TokenCounterContext($aiProvider, $model);
        $limits = $this->modelLimitsResolver->resolve($aiProvider, $model);
        $outputBudget = $this->resolveOutputBudget($limits);
        $baseSystemPrompt = $this->promptBuilder->buildSystemPrompt($context['policy_snapshot']);
        $contextBudget = $this->resolveContextBudget($limits, $baseSystemPrompt, $outputBudget, $tokenCounterContext);

        $bag->metadata['context_token_budget'] = $contextBudget;
        $bag->metadata['output_token_budget'] = $outputBudget;
        $this->tokenLimitFilter->filter($bag);

        $systemPrompt = $this->promptBuilder->buildSystemPrompt($context['policy_snapshot'], $bag->guidelines);
        $finalContextBudget = $this->resolveContextBudget($limits, $systemPrompt, $outputBudget, $tokenCounterContext);

        if ($finalContextBudget < $contextBudget) {
            $bag->metadata['context_token_budget'] = $finalContextBudget;
            $this->tokenLimitFilter->filter($bag);
            $systemPrompt = $this->promptBuilder->buildSystemPrompt($context['policy_snapshot'], $bag->guidelines);
        }

        $userPrompt = $this->promptBuilder->buildUserPromptFromBag($bag);
        $maxInputTokens = $limits->contextWindowTokens - $outputBudget - self::SAFETY_MARGIN_TOKENS;
        $preciseContext = $tokenCounterContext->withMode(TokenCountMode::Precise, $apiKey);
        $promptTokens = $this->tokenCounter->countMessageTokens($systemPrompt, $userPrompt, $preciseContext);

        if ($promptTokens > $maxInputTokens) {
            $overage = $promptTokens - $maxInputTokens;
            $bag->metadata['context_token_budget'] = max($finalContextBudget - $overage, self::MIN_CONTEXT_TOKENS);
            $this->tokenLimitFilter->filter($bag);
            $systemPrompt = $this->promptBuilder->buildSystemPrompt($context['policy_snapshot'], $bag->guidelines);
            $userPrompt = $this->promptBuilder->buildUserPromptFromBag($bag);
        }

        $promptSnapshot = $this->buildPromptSnapshot($systemPrompt, $userPrompt);
        $inputMetrics = $bag->metrics;

        $enableThinking = $aiProvider === AiProvider::Anthropic
            && config('prism.providers.anthropic.default_thinking_budget', 2048) > 0;
        $providerOptions = ['use_tool_calling' => true];

        if ($enableThinking) {
            $providerOptions['thinking'] = ['enabled' => true];
        }

        $temperature = $enableThinking ? 1 : 0.1;

        $response = Prism::structured()
            ->using($provider, $model, ['api_key' => $apiKey])
            ->withSchema($this->buildReviewSchema())
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->withMaxTokens($outputBudget)
            ->usingTemperature($temperature)
            ->withProviderOptions($providerOptions)
            ->withClientOptions(['timeout' => 420])
            ->asStructured();

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        $inputMetricsVO = new PullRequestMetrics(
            filesChanged: $inputMetrics['files_changed'] ?? 0,
            linesAdded: $inputMetrics['lines_added'] ?? 0,
            linesDeleted: $inputMetrics['lines_deleted'] ?? 0,
        );

        $promptSnapshotVO = PromptSnapshot::fromArray($promptSnapshot);

        return $this->parseStructuredResponse($response, $inputMetricsVO, $model, $provider->value, $durationMs, $promptSnapshotVO);
    }

    /**
     * Resolve provider config from policy snapshot.
     *
     * @param  array<string, mixed>  $policySnapshot
     */
    private function resolveProviderConfig(array $policySnapshot): ProviderConfig
    {
        if (isset($policySnapshot['provider']) && is_array($policySnapshot['provider'])) {
            /** @var array<string, mixed> $providerData */
            $providerData = $policySnapshot['provider'];

            return ProviderConfig::fromArray($providerData);
        }

        return ProviderConfig::default();
    }

    /**
     * Get ordered list of providers to try based on config and availability.
     *
     * @return array<int, AiProvider>
     */
    private function getProvidersToTry(Repository $repository, ProviderConfig $providerConfig): array
    {
        $availableProviders = $this->keyResolver->getAvailableProviders($repository);

        if ($availableProviders === []) {
            return [];
        }

        // If preferred provider is set and available, put it first
        if ($providerConfig->preferred instanceof AiProvider) {
            $preferred = $providerConfig->preferred;

            if ($this->keyResolver->hasProvider($repository, $preferred)) {
                // Move preferred to front, keep others for fallback
                $others = array_filter($availableProviders, fn (AiProvider $p): bool => $p !== $preferred);

                return [$preferred, ...array_values($others)];
            }

            // Preferred not available - if no fallback, return empty
            if (! $providerConfig->fallback) {
                return [];
            }
        }

        return $availableProviders;
    }

    /**
     * Map AiProvider enum to Prism Provider enum.
     */
    private function mapToProvider(AiProvider $aiProvider): Provider
    {
        return match ($aiProvider) {
            AiProvider::Anthropic => Provider::Anthropic,
            AiProvider::OpenAI => Provider::OpenAI,
        };
    }

    /**
     * Resolve the AI model based on the provider, config, and user selection.
     */
    private function resolveModel(AiProvider $aiProvider, ProviderConfig $providerConfig, ?ProviderKey $providerKey): string
    {
        // 1. Use model from provider key if user selected one
        if ($providerKey?->providerModel !== null) {
            return $providerKey->providerModel->identifier;
        }

        // 2. Use model from sentinel.yaml config if set for this provider
        if ($providerConfig->model !== null && $providerConfig->preferred === $aiProvider) {
            return $providerConfig->model;
        }

        // 3. Get default from database
        $defaultModel = AiOption::getDefault($aiProvider);
        if ($defaultModel instanceof AiOption) {
            return $defaultModel->identifier;
        }

        // 4. Hardcoded fallback (safety net)
        return match ($aiProvider) {
            AiProvider::Anthropic => 'claude-sonnet-4-5-20250929',
            AiProvider::OpenAI => 'gpt-4o',
        };
    }

    /**
     * Resolve max output tokens for the current model.
     */
    private function resolveOutputBudget(ModelLimits $limits): int
    {
        return min(self::DEFAULT_OUTPUT_TOKENS, $limits->maxOutputTokens);
    }

    /**
     * Resolve the max context tokens for the review prompt.
     */
    private function resolveContextBudget(
        ModelLimits $limits,
        string $systemPrompt,
        int $outputBudget,
        TokenCounterContext $tokenCounterContext
    ): int {
        $systemTokens = $this->tokenCounter->countTextTokens($systemPrompt, $tokenCounterContext);
        $budget = $limits->contextWindowTokens - $systemTokens - $outputBudget - self::SAFETY_MARGIN_TOKENS;

        return max($budget, self::MIN_CONTEXT_TOKENS);
    }

    /**
     * Build the JSON schema for structured review output.
     */
    private function buildReviewSchema(): ObjectSchema
    {
        $summarySchema = new ObjectSchema(
            name: 'summary',
            description: 'Overall review summary',
            properties: [
                new StringSchema('overview', 'Comprehensive overview starting with methodology checklist'),
                new EnumSchema('verdict', 'Review verdict', ReviewVerdict::values()),
                new EnumSchema('risk_level', 'Overall risk level', RiskLevel::values()),
                new ArraySchema('strengths', 'List of positive aspects', new StringSchema('strength', 'A strength')),
                new ArraySchema('concerns', 'List of concerns', new StringSchema('concern', 'A concern')),
                new ArraySchema('recommendations', 'List of recommendations', new StringSchema('recommendation', 'A recommendation')),
            ],
            requiredFields: ['overview', 'verdict', 'risk_level'],
        );

        $findingSchema = new ObjectSchema(
            name: 'finding',
            description: 'A single code review finding',
            properties: [
                new EnumSchema('severity', 'Severity level', SentinelConfigSeverity::values()),
                new EnumSchema('category', 'Finding category', FindingCategory::values()),
                new StringSchema('title', 'Short title of the finding'),
                new StringSchema('description', 'Detailed description of the issue'),
                new NumberSchema('confidence', 'Confidence score between 0 and 1'),
                new StringSchema('impact', 'Why this matters and its potential impact'),
                new StringSchema('file_path', 'Path to the file'),
                new NumberSchema('line_start', 'Starting line number'),
                new NumberSchema('line_end', 'Ending line number'),
                new StringSchema('current_code', 'The current code that needs to be changed'),
                new StringSchema('replacement_code', 'The suggested replacement code'),
                new StringSchema('explanation', 'Explanation of the code change'),
                new ArraySchema('references', 'Sources: markdown links [Text](url), repo guidelines, or plain text standards', new StringSchema('reference', 'A reference source')),
            ],
            requiredFields: ['severity', 'category', 'title', 'description', 'confidence'],
        );

        return new ObjectSchema(
            name: 'review_response',
            description: 'Complete code review response',
            properties: [
                $summarySchema,
                new ArraySchema('findings', 'List of findings', $findingSchema),
            ],
            requiredFields: ['summary', 'findings'],
        );
    }

    /**
     * Parse the structured AI response.
     */
    private function parseStructuredResponse(
        StructuredResponse $response,
        PullRequestMetrics $inputMetrics,
        string $model,
        string $provider,
        int $durationMs,
        PromptSnapshot $promptSnapshot
    ): ReviewResult {
        /** @var array<string, mixed> $parsed */
        $parsed = $response->structured;

        $rawSummary = $parsed['summary'] ?? [];
        $rawFindings = $parsed['findings'] ?? [];

        /** @var array<string, mixed> $summaryData */
        $summaryData = is_array($rawSummary) ? $rawSummary : [];
        /** @var array<int, mixed> $findingsData */
        $findingsData = is_array($rawFindings) ? $rawFindings : [];

        $summary = $this->normalizeSummaryToVO($summaryData);
        $findings = $this->normalizeFindingsToVOs($findingsData);

        $inputTokens = $response->usage->promptTokens;
        $outputTokens = $response->usage->completionTokens;

        return new ReviewResult(
            summary: $summary,
            findings: $findings,
            metrics: new ReviewMetrics(
                filesChanged: $inputMetrics->filesChanged,
                linesAdded: $inputMetrics->linesAdded,
                linesDeleted: $inputMetrics->linesDeleted,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                tokensUsedEstimated: $inputTokens + $outputTokens,
                model: $model,
                provider: $provider,
                durationMs: $durationMs,
            ),
            promptSnapshot: $promptSnapshot,
        );
    }

    /**
     * Build a snapshot of the prompts used for this review.
     *
     * @return array{system: array{version: string, hash: string}, user: array{version: string, hash: string}, hash_algorithm: string}
     */
    private function buildPromptSnapshot(string $systemPrompt, string $userPrompt): array
    {
        return [
            'system' => [
                'version' => ReviewPromptBuilder::SYSTEM_PROMPT_VERSION,
                'hash' => hash('sha256', $systemPrompt),
            ],
            'user' => [
                'version' => ReviewPromptBuilder::USER_PROMPT_VERSION,
                'hash' => hash('sha256', $userPrompt),
            ],
            'hash_algorithm' => 'sha256',
        ];
    }

    /**
     * Normalize and validate the summary structure to a VO.
     *
     * @param  array<string, mixed>  $summary
     */
    private function normalizeSummaryToVO(array $summary): ReviewSummary
    {
        $rawVerdict = $summary['verdict'] ?? null;
        $rawRiskLevel = $summary['risk_level'] ?? null;

        $verdict = is_string($rawVerdict)
            ? (ReviewVerdict::tryFrom($rawVerdict) ?? ReviewVerdict::Comment)
            : ReviewVerdict::Comment;

        $riskLevel = is_string($rawRiskLevel)
            ? (RiskLevel::tryFrom($rawRiskLevel) ?? RiskLevel::Low)
            : RiskLevel::Low;

        return new ReviewSummary(
            overview: is_string($summary['overview'] ?? null) ? $summary['overview'] : 'Review completed.',
            verdict: $verdict,
            riskLevel: $riskLevel,
            strengths: $this->filterStringArray($summary['strengths'] ?? []),
            concerns: $this->filterStringArray($summary['concerns'] ?? []),
            recommendations: $this->filterStringArray($summary['recommendations'] ?? []),
        );
    }

    /**
     * Filter an array to only include string values.
     *
     * @return array<int, string>
     */
    private function filterStringArray(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, is_string(...)));
    }

    /**
     * Normalize and validate the findings array to VOs.
     *
     * @param  array<int, mixed>  $findings
     * @return array<int, ReviewFinding>
     */
    private function normalizeFindingsToVOs(array $findings): array
    {
        $normalized = [];

        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            /** @var array<string, mixed> $finding */
            $normalizedFinding = $this->normalizeFindingToVO($finding);

            if ($normalizedFinding instanceof ReviewFinding) {
                $normalized[] = $normalizedFinding;
            }
        }

        return $normalized;
    }

    /**
     * Normalize a single finding to a VO.
     *
     * @param  array<string, mixed>  $finding
     */
    private function normalizeFindingToVO(array $finding): ?ReviewFinding
    {
        if (
            ! isset($finding['severity'], $finding['category'], $finding['title'], $finding['description'])
            || ! is_string($finding['severity'])
            || ! is_string($finding['category'])
            || ! is_string($finding['title'])
            || ! is_string($finding['description'])
        ) {
            return null;
        }

        $severity = SentinelConfigSeverity::tryFrom($finding['severity']) ?? SentinelConfigSeverity::Info;
        $category = FindingCategory::tryFrom($finding['category']) ?? FindingCategory::Maintainability;

        $confidence = isset($finding['confidence']) && is_numeric($finding['confidence'])
            ? max(0.0, min(1.0, (float) $finding['confidence']))
            : 0.5;

        $references = [];
        if (isset($finding['references']) && is_array($finding['references'])) {
            $references = array_values(array_filter($finding['references'], is_string(...)));
        }

        return new ReviewFinding(
            severity: $severity,
            category: $category,
            title: $finding['title'],
            description: $finding['description'],
            impact: isset($finding['impact']) && is_string($finding['impact']) ? $finding['impact'] : '',
            confidence: $confidence,
            filePath: isset($finding['file_path']) && is_string($finding['file_path']) ? $finding['file_path'] : null,
            lineStart: isset($finding['line_start']) && is_int($finding['line_start']) ? $finding['line_start'] : null,
            lineEnd: isset($finding['line_end']) && is_int($finding['line_end']) ? $finding['line_end'] : null,
            currentCode: isset($finding['current_code']) && is_string($finding['current_code']) ? $finding['current_code'] : null,
            replacementCode: isset($finding['replacement_code']) && is_string($finding['replacement_code']) ? $finding['replacement_code'] : null,
            explanation: isset($finding['explanation']) && is_string($finding['explanation']) ? $finding['explanation'] : null,
            references: $references,
        );
    }
}
