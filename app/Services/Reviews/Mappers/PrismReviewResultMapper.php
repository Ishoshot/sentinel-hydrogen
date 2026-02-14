<?php

declare(strict_types=1);

namespace App\Services\Reviews\Mappers;

use App\Enums\Reviews\FindingCategory;
use App\Enums\Reviews\ReviewVerdict;
use App\Enums\Reviews\RiskLevel;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewSummary;

/**
 * Maps raw structured payload arrays to review value objects.
 */
final class PrismReviewResultMapper
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function mapSummary(array $summary): ReviewSummary
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
     * @param  array<int, mixed>  $findings
     * @return array<int, ReviewFinding>
     */
    public function mapFindings(array $findings): array
    {
        $normalizedFindings = [];

        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            /** @var array<string, mixed> $finding */
            $normalizedFinding = $this->mapFinding($finding);
            if ($normalizedFinding instanceof ReviewFinding) {
                $normalizedFindings[] = $normalizedFinding;
            }
        }

        return $normalizedFindings;
    }

    /**
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
     * @param  array<string, mixed>  $finding
     */
    private function mapFinding(array $finding): ?ReviewFinding
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
