<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

use App\Enums\Reviews\ReviewVerdict;
use App\Enums\Reviews\RiskLevel;

/**
 * Summary of a code review.
 */
final readonly class ReviewSummary
{
    /**
     * Create a new ReviewSummary instance.
     *
     * @param  array<int, string>  $strengths
     * @param  array<int, string>  $concerns
     * @param  array<int, string>  $recommendations
     */
    public function __construct(
        public string $overview,
        public ReviewVerdict $verdict,
        public RiskLevel $riskLevel,
        public array $strengths = [],
        public array $concerns = [],
        public array $recommendations = [],
    ) {}

    /**
     * Create from array.
     *
     * @param  array{overview: string, verdict: string, risk_level: string, strengths?: array<int, string>, concerns?: array<int, string>, recommendations?: array<int, string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            overview: $data['overview'],
            verdict: ReviewVerdict::tryFrom($data['verdict']) ?? ReviewVerdict::Comment,
            riskLevel: RiskLevel::tryFrom($data['risk_level']) ?? RiskLevel::Low,
            strengths: $data['strengths'] ?? [],
            concerns: $data['concerns'] ?? [],
            recommendations: $data['recommendations'] ?? [],
        );
    }

    /**
     * Check if the review has a high risk level.
     */
    public function isHighRisk(): bool
    {
        return $this->riskLevel === RiskLevel::High || $this->riskLevel === RiskLevel::Critical;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{overview: string, verdict: string, risk_level: string, strengths: array<int, string>, concerns: array<int, string>, recommendations: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'overview' => $this->overview,
            'verdict' => $this->verdict->value,
            'risk_level' => $this->riskLevel->value,
            'strengths' => $this->strengths,
            'concerns' => $this->concerns,
            'recommendations' => $this->recommendations,
        ];
    }
}
