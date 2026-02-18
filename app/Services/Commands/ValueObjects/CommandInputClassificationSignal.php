<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

use App\Enums\Commands\CommandInputRiskLevel;

final readonly class CommandInputClassificationSignal
{
    /**
     * Create a new signal value object.
     */
    public function __construct(
        public string $source,
        public string $code,
        public CommandInputRiskLevel $severity,
        public string $evidence,
    ) {}

    /**
     * @param  array{source?: mixed, code?: mixed, severity?: mixed, evidence?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        $rawSeverity = is_string($data['severity'] ?? null) ? $data['severity'] : CommandInputRiskLevel::Low->value;

        return new self(
            source: is_string($data['source'] ?? null) ? $data['source'] : 'rule',
            code: is_string($data['code'] ?? null) ? $data['code'] : 'UNSPECIFIED_SIGNAL',
            severity: CommandInputRiskLevel::tryFrom($rawSeverity) ?? CommandInputRiskLevel::Low,
            evidence: is_string($data['evidence'] ?? null) ? $data['evidence'] : '',
        );
    }

    /**
     * @return array{source: string, code: string, severity: string, evidence: string}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'code' => $this->code,
            'severity' => $this->severity->value,
            'evidence' => $this->evidence,
        ];
    }
}
