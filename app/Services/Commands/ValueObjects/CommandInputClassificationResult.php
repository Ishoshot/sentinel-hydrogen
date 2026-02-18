<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

use App\Enums\Commands\CommandInputDecision;
use App\Enums\Commands\CommandInputRiskLevel;

final readonly class CommandInputClassificationResult
{
    /**
     * @var array<int, string>
     */
    private const array ALLOWED_RISK_TYPES = [
        'prompt_injection',
        'data_exfiltration',
        'secret_exposure',
        'privilege_escalation',
        'policy_bypass',
        'malicious_code_intent',
    ];

    /**
     * @param  array<int, string>  $riskTypes
     * @param  array<int, CommandInputClassificationSignal>  $signals
     */
    public function __construct(
        public CommandInputDecision $decision,
        public CommandInputRiskLevel $riskLevel,
        public array $riskTypes,
        public float $confidence,
        public string $summary,
        public array $signals,
    ) {}

    /**
     * @param  array{
     *     verdict?: mixed,
     *     decision?: mixed,
     *     risk_level?: mixed,
     *     risk_types?: mixed,
     *     confidence?: mixed,
     *     summary?: mixed,
     *     signals?: mixed
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $rawDecision = is_string($data['decision'] ?? null)
            ? $data['decision']
            : (is_string($data['verdict'] ?? null) ? $data['verdict'] : CommandInputDecision::Allow->value);
        $rawRiskLevel = is_string($data['risk_level'] ?? null) ? $data['risk_level'] : CommandInputRiskLevel::Low->value;
        $riskTypes = is_array($data['risk_types'] ?? null)
            ? array_values(array_unique(array_filter(
                $data['risk_types'],
                static fn (mixed $riskType): bool => is_string($riskType) && in_array($riskType, self::ALLOWED_RISK_TYPES, true)
            )))
            : [];
        $rawSignals = is_array($data['signals'] ?? null) ? $data['signals'] : [];

        $signals = [];
        foreach ($rawSignals as $signal) {
            if (! is_array($signal)) {
                continue;
            }

            $signals[] = CommandInputClassificationSignal::fromArray($signal);
        }

        $rawConfidence = is_numeric($data['confidence'] ?? null) ? (float) $data['confidence'] : 0.0;

        return new self(
            decision: CommandInputDecision::tryFrom($rawDecision) ?? CommandInputDecision::Allow,
            riskLevel: CommandInputRiskLevel::tryFrom($rawRiskLevel) ?? CommandInputRiskLevel::Low,
            riskTypes: $riskTypes,
            confidence: max(0.0, min(1.0, $rawConfidence)),
            summary: is_string($data['summary'] ?? null) ? mb_trim($data['summary']) : '',
            signals: $signals,
        );
    }

    /**
     * Create a default allow result.
     */
    public static function allow(string $summary = 'No elevated risk patterns detected.'): self
    {
        return new self(
            decision: CommandInputDecision::Allow,
            riskLevel: CommandInputRiskLevel::Low,
            riskTypes: [],
            confidence: 0.0,
            summary: $summary,
            signals: [],
        );
    }

    /**
     * Determine whether this result blocks execution.
     */
    public function blocksExecution(): bool
    {
        return $this->decision === CommandInputDecision::Block;
    }

    /**
     * Determine whether this result requires caution mode.
     */
    public function requiresCaution(): bool
    {
        return $this->decision === CommandInputDecision::Caution;
    }

    /**
     * @return array{
     *     decision: string,
     *     risk_level: string,
     *     risk_types: array<int, string>,
     *     confidence: float,
     *     summary: string,
     *     signals: array<int, array{source: string, code: string, severity: string, evidence: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision->value,
            'risk_level' => $this->riskLevel->value,
            'risk_types' => $this->riskTypes,
            'confidence' => $this->confidence,
            'summary' => $this->summary,
            'signals' => array_map(
                static fn (CommandInputClassificationSignal $signal): array => $signal->toArray(),
                $this->signals
            ),
        ];
    }

    /**
     * Build a safe context payload for storage and prompt augmentation.
     *
     * This intentionally excludes free-text explanation/evidence fields to
     * avoid persisting or re-injecting untrusted strings into downstream prompts.
     *
     * @return array{
     *     decision: string,
     *     risk_level: string,
     *     risk_types: array<int, string>,
     *     confidence: float,
     *     signals: array<int, array{source: string, code: string, severity: string}>
     * }
     */
    public function toSafeContextArray(): array
    {
        return [
            'decision' => $this->decision->value,
            'risk_level' => $this->riskLevel->value,
            'risk_types' => $this->riskTypes,
            'confidence' => $this->confidence,
            'signals' => array_map(
                static fn (CommandInputClassificationSignal $signal): array => [
                    'source' => $signal->source,
                    'code' => $signal->code,
                    'severity' => $signal->severity->value,
                ],
                $this->signals
            ),
        ];
    }
}
