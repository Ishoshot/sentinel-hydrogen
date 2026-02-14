<?php

declare(strict_types=1);

namespace App\Services\Reviews\Parsers;

use App\Services\Reviews\Mappers\PrismReviewResultMapper;
use App\Services\Reviews\ValueObjects\PromptSnapshot;
use App\Services\Reviews\ValueObjects\PullRequestMetrics;
use App\Services\Reviews\ValueObjects\ReviewMetrics;
use App\Services\Reviews\ValueObjects\ReviewResult;
use Prism\Prism\Structured\Response as StructuredResponse;

final readonly class PrismReviewResponseParser
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private PrismReviewResultMapper $resultMapper,
    ) {}

    /**
     * Parse a structured provider response into a domain review result.
     */
    public function parseStructuredResponse(
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

        $summary = $this->resultMapper->mapSummary($summaryData);
        $findings = $this->resultMapper->mapFindings($findingsData);

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
}
