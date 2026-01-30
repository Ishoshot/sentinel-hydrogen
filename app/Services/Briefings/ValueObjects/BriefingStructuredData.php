<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

final readonly class BriefingStructuredData
{
    /**
     * Create structured briefing data from resolved value objects.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public BriefingPeriod $period,
        public BriefingSummary $summary,
        public ?BriefingTopContributor $topContributor,
        public BriefingDataQuality $dataQuality,
        public BriefingEvidence $evidence,
        public array $data = [],
    ) {}

    /**
     * Create structured data from a raw array payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var array<string, mixed> $periodPayload */
        $periodPayload = is_array($payload['period'] ?? null) ? $payload['period'] : [];
        /** @var array<string, mixed> $summaryPayload */
        $summaryPayload = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        /** @var array<string, mixed>|null $topContributorPayload */
        $topContributorPayload = is_array($payload['top_contributor'] ?? null)
            ? $payload['top_contributor']
            : null;
        /** @var array<string, mixed> $dataQualityPayload */
        $dataQualityPayload = is_array($payload['data_quality'] ?? null) ? $payload['data_quality'] : [];
        /** @var array<string, mixed> $evidencePayload */
        $evidencePayload = is_array($payload['evidence'] ?? null) ? $payload['evidence'] : [];

        $period = BriefingPeriod::fromArray($periodPayload);
        $summary = BriefingSummary::fromArray($summaryPayload);
        $topContributor = BriefingTopContributor::fromArray($topContributorPayload);
        $dataQuality = BriefingDataQuality::fromArray($dataQualityPayload);
        $evidence = BriefingEvidence::fromArray($evidencePayload);

        $data = $payload;
        unset(
            $data['period'],
            $data['summary'],
            $data['top_contributor'],
            $data['data_quality'],
            $data['evidence'],
        );

        return new self($period, $summary, $topContributor, $dataQuality, $evidence, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => $this->period->toArray(),
            'summary' => $this->summary->toArray(),
            'top_contributor' => $this->topContributor?->toArray(),
            ...$this->data,
            'data_quality' => $this->dataQuality->toArray(),
            'evidence' => $this->evidence->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->data;
    }

    /**
     * Get the briefing summary value object.
     */
    public function summary(): BriefingSummary
    {
        return $this->summary;
    }

    /**
     * Get the briefing period value object.
     */
    public function period(): BriefingPeriod
    {
        return $this->period;
    }

    /**
     * Get the top contributor value object, when available.
     */
    public function topContributor(): ?BriefingTopContributor
    {
        return $this->topContributor;
    }

    /**
     * Get a value from the payload.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'period' => $this->period->toArray(),
            'summary' => $this->summary->toArray(),
            'top_contributor' => $this->topContributor?->toArray(),
            'data_quality' => $this->dataQuality->toArray(),
            'evidence' => $this->evidence->toArray(),
            default => $this->data[$key] ?? $default,
        };
    }
}
