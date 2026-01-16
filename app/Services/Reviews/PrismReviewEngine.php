<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\DataTransferObjects\SentinelConfig\ProviderConfig;
use App\Enums\AiProvider;
use App\Enums\FindingCategory;
use App\Enums\ReviewVerdict;
use App\Enums\RiskLevel;
use App\Enums\SentinelConfigSeverity;
use App\Exceptions\NoProviderKeyException;
use App\Models\AiOption;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\Context\ContextBag;
use App\Services\Reviews\Contracts\ProviderKeyResolver;
use App\Services\Reviews\Contracts\ReviewEngine;
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

    /**
     * Create a new engine instance.
     */
    public function __construct(
        private ReviewPromptBuilder $promptBuilder,
        private ProviderKeyResolver $keyResolver,
    ) {}

    /**
     * Perform AI-powered code review using ContextBag.
     *
     * Uses BYOK provider keys from repository configuration.
     * Supports provider preferences and fallback retry logic.
     *
     * @param  array{repository: Repository, policy_snapshot: array<string, mixed>, context_bag: ContextBag}  $context
     * @return array{summary: array<string, mixed>, findings: array<int, array<string, mixed>>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, input_tokens: int, output_tokens: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}}
     *
     * @throws NoProviderKeyException When no BYOK provider keys are configured for the repository
     */
    public function review(array $context): array
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
     * @return array{summary: array<string, mixed>, findings: array<int, array<string, mixed>>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, input_tokens: int, output_tokens: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}}
     */
    private function executeReview(array $context, AiProvider $aiProvider, ProviderConfig $providerConfig): array
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

        $systemPrompt = $this->promptBuilder->buildSystemPrompt($context['policy_snapshot']);

        $bag = $context['context_bag'];
        $userPrompt = $this->promptBuilder->buildUserPromptFromBag($bag);
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
            ->withMaxTokens(8192)
            ->usingTemperature($temperature)
            ->withProviderOptions($providerOptions)
            ->withClientOptions(['timeout' => 420])
            ->asStructured();

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        /** @var array{files_changed: int, lines_added: int, lines_deleted: int} $metricsForParsing */
        $metricsForParsing = [
            'files_changed' => $inputMetrics['files_changed'] ?? 0,
            'lines_added' => $inputMetrics['lines_added'] ?? 0,
            'lines_deleted' => $inputMetrics['lines_deleted'] ?? 0,
        ];

        return $this->parseStructuredResponse($response, $metricsForParsing, $model, $provider->value, $durationMs);
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
     *
     * @param  array{files_changed: int, lines_added: int, lines_deleted: int}  $inputMetrics
     * @return array{summary: array<string, mixed>, findings: array<int, array<string, mixed>>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, input_tokens: int, output_tokens: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}}
     */
    private function parseStructuredResponse(
        StructuredResponse $response,
        array $inputMetrics,
        string $model,
        string $provider,
        int $durationMs
    ): array {
        /** @var array<string, mixed> $parsed */
        $parsed = $response->structured;

        $rawSummary = $parsed['summary'] ?? [];
        $rawFindings = $parsed['findings'] ?? [];

        /** @var array<string, mixed> $summaryData */
        $summaryData = is_array($rawSummary) ? $rawSummary : [];
        /** @var array<int, mixed> $findingsData */
        $findingsData = is_array($rawFindings) ? $rawFindings : [];

        $summary = $this->normalizeSummary($summaryData);
        $findings = $this->normalizeFindings($findingsData);

        $inputTokens = $response->usage->promptTokens;
        $outputTokens = $response->usage->completionTokens;

        return [
            'summary' => $summary,
            'findings' => $findings,
            'metrics' => [
                'files_changed' => $inputMetrics['files_changed'],
                'lines_added' => $inputMetrics['lines_added'],
                'lines_deleted' => $inputMetrics['lines_deleted'],
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'tokens_used_estimated' => $inputTokens + $outputTokens,
                'model' => $model,
                'provider' => $provider,
                'duration_ms' => $durationMs,
            ],
        ];
    }

    /**
     * Normalize and validate the summary structure.
     *
     * @param  array<string, mixed>  $summary
     * @return array{overview: string, verdict: string, risk_level: string, strengths: array<int, string>, concerns: array<int, string>, recommendations: array<int, string>}
     */
    private function normalizeSummary(array $summary): array
    {
        $rawVerdict = $summary['verdict'] ?? null;
        $rawRiskLevel = $summary['risk_level'] ?? null;

        return [
            'overview' => is_string($summary['overview'] ?? null) ? $summary['overview'] : 'Review completed.',
            'verdict' => is_string($rawVerdict) && in_array($rawVerdict, ReviewVerdict::values(), true)
                ? $rawVerdict
                : ReviewVerdict::Comment->value,
            'risk_level' => is_string($rawRiskLevel) && in_array($rawRiskLevel, RiskLevel::values(), true)
                ? $rawRiskLevel
                : RiskLevel::Low->value,
            'strengths' => $this->filterStringArray($summary['strengths'] ?? []),
            'concerns' => $this->filterStringArray($summary['concerns'] ?? []),
            'recommendations' => $this->filterStringArray($summary['recommendations'] ?? []),
        ];
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
     * Normalize and validate the findings array.
     *
     * @param  array<int, mixed>  $findings
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFindings(array $findings): array
    {
        $normalized = [];

        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            /** @var array<string, mixed> $finding */
            $normalizedFinding = $this->normalizeFinding($finding);

            if ($normalizedFinding !== null) {
                $normalized[] = $normalizedFinding;
            }
        }

        return $normalized;
    }

    /**
     * Normalize a single finding.
     *
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>|null
     */
    private function normalizeFinding(array $finding): ?array
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

        $severity = in_array($finding['severity'], SentinelConfigSeverity::values(), true)
            ? $finding['severity']
            : SentinelConfigSeverity::Info->value;

        $category = in_array($finding['category'], FindingCategory::values(), true)
            ? $finding['category']
            : FindingCategory::Maintainability->value;

        $confidence = isset($finding['confidence']) && is_numeric($finding['confidence'])
            ? max(0.0, min(1.0, (float) $finding['confidence']))
            : 0.5;

        $impact = isset($finding['impact']) && is_string($finding['impact'])
            ? $finding['impact']
            : '';

        $result = [
            'severity' => $severity,
            'category' => $category,
            'title' => $finding['title'],
            'description' => $finding['description'],
            'impact' => $impact,
            'confidence' => $confidence,
        ];

        // Location fields
        if (isset($finding['file_path']) && is_string($finding['file_path'])) {
            $result['file_path'] = $finding['file_path'];
        }

        if (isset($finding['line_start']) && is_int($finding['line_start'])) {
            $result['line_start'] = $finding['line_start'];
        }

        if (isset($finding['line_end']) && is_int($finding['line_end'])) {
            $result['line_end'] = $finding['line_end'];
        }

        // Code replacement fields (new enhanced structure)
        if (isset($finding['current_code']) && is_string($finding['current_code'])) {
            $result['current_code'] = $finding['current_code'];
        }

        if (isset($finding['replacement_code']) && is_string($finding['replacement_code'])) {
            $result['replacement_code'] = $finding['replacement_code'];
        }

        if (isset($finding['explanation']) && is_string($finding['explanation'])) {
            $result['explanation'] = $finding['explanation'];
        }

        // References
        if (isset($finding['references']) && is_array($finding['references'])) {
            $references = array_filter($finding['references'], is_string(...));
            if ($references !== []) {
                $result['references'] = array_values($references);
            }
        }

        return $result;
    }
}
