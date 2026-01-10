<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Services\Context\ContextBag;
use App\Services\Reviews\Contracts\ReviewEngine;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use RuntimeException;

/**
 * AI-powered review engine using PrismPHP for LLM integration.
 */
final readonly class PrismReviewEngine implements ReviewEngine
{
    private const array VALID_SEVERITIES = ['info', 'low', 'medium', 'high', 'critical'];

    private const array VALID_CATEGORIES = [
        'security',
        'correctness',
        'reliability',
        'performance',
        'maintainability',
        'testing',
        'style',
        'documentation',
    ];

    private const array VALID_RISK_LEVELS = ['low', 'medium', 'high', 'critical'];

    /**
     * Create a new engine instance.
     */
    public function __construct(private ReviewPromptBuilder $promptBuilder) {}

    /**
     * Perform AI-powered code review using ContextBag.
     *
     * @param  array{policy_snapshot: array<string, mixed>, context_bag: ContextBag}  $context
     * @return array{summary: array{overview: string, risk_level: string, recommendations: array<int, string>}, findings: array<int, array{severity: string, category: string, title: string, description: string, rationale: string, confidence: float, file_path?: string, line_start?: int, line_end?: int, suggestion?: string, patch?: string, references?: array<int, string>, tags?: array<int, string>}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}}
     */
    public function review(array $context): array
    {
        $startTime = microtime(true);

        $provider = $this->resolveProvider();
        $model = $this->resolveModel();

        $systemPrompt = $this->promptBuilder->buildSystemPrompt($context['policy_snapshot']);

        $bag = $context['context_bag'];
        $userPrompt = $this->promptBuilder->buildUserPromptFromBag($bag);
        $inputMetrics = $bag->metrics;

        $response = Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->withMaxTokens(4096)
            ->usingTemperature(0.1)
            ->asText();

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        /** @var array{files_changed: int, lines_added: int, lines_deleted: int} $metricsForParsing */
        $metricsForParsing = [
            'files_changed' => $inputMetrics['files_changed'] ?? 0,
            'lines_added' => $inputMetrics['lines_added'] ?? 0,
            'lines_deleted' => $inputMetrics['lines_deleted'] ?? 0,
        ];

        return $this->parseResponse($response, $metricsForParsing, $model, $provider->value, $durationMs);
    }

    /**
     * Resolve the AI provider based on available API keys.
     */
    private function resolveProvider(): Provider
    {
        $providerKey = config('prism.providers.anthropic.api_key', '');

        if ($providerKey !== '') {
            return Provider::Anthropic;
        }

        $openAiKey = config('prism.providers.openai.api_key', '');

        if ($openAiKey !== '') {
            return Provider::OpenAI;
        }

        return Provider::Anthropic;
    }

    /**
     * Resolve the AI model based on the provider.
     */
    private function resolveModel(): string
    {
        $provider = $this->resolveProvider();

        return match ($provider) {
            Provider::Anthropic => 'claude-sonnet-4-20250514',
            Provider::OpenAI => 'gpt-4o',
            default => 'claude-sonnet-4-20250514',
        };
    }

    /**
     * Parse the AI response and validate against the expected schema.
     *
     * @param  array{files_changed: int, lines_added: int, lines_deleted: int}  $inputMetrics
     * @return array{summary: array{overview: string, risk_level: string, recommendations: array<int, string>}, findings: array<int, array{severity: string, category: string, title: string, description: string, rationale: string, confidence: float, file_path?: string, line_start?: int, line_end?: int, suggestion?: string, patch?: string, references?: array<int, string>, tags?: array<int, string>}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}}
     */
    private function parseResponse(
        TextResponse $response,
        array $inputMetrics,
        string $model,
        string $provider,
        int $durationMs
    ): array {
        $responseText = mb_trim($response->text);

        if (str_starts_with($responseText, '```json')) {
            $responseText = mb_substr($responseText, 7);
        }

        if (str_starts_with($responseText, '```')) {
            $responseText = mb_substr($responseText, 3);
        }

        if (str_ends_with($responseText, '```')) {
            $responseText = mb_substr($responseText, 0, -3);
        }

        $responseText = mb_trim($responseText);

        $parsed = json_decode($responseText, true);

        if (! is_array($parsed)) {
            throw new RuntimeException('Failed to parse AI response as JSON: '.json_last_error_msg());
        }

        $rawSummary = $parsed['summary'] ?? [];
        $rawFindings = $parsed['findings'] ?? [];

        /** @var array<string, mixed> $summaryData */
        $summaryData = is_array($rawSummary) ? $rawSummary : [];
        /** @var array<int, mixed> $findingsData */
        $findingsData = is_array($rawFindings) ? $rawFindings : [];

        $summary = $this->normalizeSummary($summaryData);
        $findings = $this->normalizeFindings($findingsData);

        $tokensUsed = $response->usage->promptTokens + $response->usage->completionTokens;

        return [
            'summary' => $summary,
            'findings' => $findings,
            'metrics' => [
                'files_changed' => $inputMetrics['files_changed'],
                'lines_added' => $inputMetrics['lines_added'],
                'lines_deleted' => $inputMetrics['lines_deleted'],
                'tokens_used_estimated' => $tokensUsed,
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
     * @return array{overview: string, risk_level: string, recommendations: array<int, string>}
     */
    private function normalizeSummary(array $summary): array
    {
        $overview = isset($summary['overview']) && is_string($summary['overview'])
            ? $summary['overview']
            : 'Review completed.';

        $riskLevel = isset($summary['risk_level']) && is_string($summary['risk_level'])
            && in_array($summary['risk_level'], self::VALID_RISK_LEVELS, true)
            ? $summary['risk_level']
            : 'low';

        $recommendations = [];
        if (isset($summary['recommendations']) && is_array($summary['recommendations'])) {
            foreach ($summary['recommendations'] as $rec) {
                if (is_string($rec)) {
                    $recommendations[] = $rec;
                }
            }
        }

        return [
            'overview' => $overview,
            'risk_level' => $riskLevel,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Normalize and validate the findings array.
     *
     * @param  array<int, mixed>  $findings
     * @return array<int, array{severity: string, category: string, title: string, description: string, rationale: string, confidence: float, file_path?: string, line_start?: int, line_end?: int, suggestion?: string, patch?: string, references?: array<int, string>, tags?: array<int, string>}>
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
     * @return array{severity: string, category: string, title: string, description: string, rationale: string, confidence: float, file_path?: string, line_start?: int, line_end?: int, suggestion?: string, patch?: string, references?: array<int, string>, tags?: array<int, string>}|null
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

        $severity = in_array($finding['severity'], self::VALID_SEVERITIES, true)
            ? $finding['severity']
            : 'info';

        $category = in_array($finding['category'], self::VALID_CATEGORIES, true)
            ? $finding['category']
            : 'maintainability';

        $confidence = isset($finding['confidence']) && is_numeric($finding['confidence'])
            ? max(0.0, min(1.0, (float) $finding['confidence']))
            : 0.5;

        $rationale = isset($finding['rationale']) && is_string($finding['rationale'])
            ? $finding['rationale']
            : '';

        $result = [
            'severity' => $severity,
            'category' => $category,
            'title' => $finding['title'],
            'description' => $finding['description'],
            'rationale' => $rationale,
            'confidence' => $confidence,
        ];

        if (isset($finding['file_path']) && is_string($finding['file_path'])) {
            $result['file_path'] = $finding['file_path'];
        }

        if (isset($finding['line_start']) && is_int($finding['line_start'])) {
            $result['line_start'] = $finding['line_start'];
        }

        if (isset($finding['line_end']) && is_int($finding['line_end'])) {
            $result['line_end'] = $finding['line_end'];
        }

        if (isset($finding['suggestion']) && is_string($finding['suggestion'])) {
            $result['suggestion'] = $finding['suggestion'];
        }

        if (isset($finding['patch']) && is_string($finding['patch'])) {
            $result['patch'] = $finding['patch'];
        }

        if (isset($finding['references']) && is_array($finding['references'])) {
            $references = array_filter($finding['references'], is_string(...));
            if ($references !== []) {
                $result['references'] = array_values($references);
            }
        }

        if (isset($finding['tags']) && is_array($finding['tags'])) {
            $tags = array_filter($finding['tags'], is_string(...));
            if ($tags !== []) {
                $result['tags'] = array_values($tags);
            }
        }

        return $result;
    }
}
