<?php

declare(strict_types=1);

namespace App\Services\Briefings\Templates;

use App\Services\Briefings\Contracts\BriefingTemplateDataCollector;
use App\Services\Briefings\ValueObjects\BriefingDateRange;
use App\Services\Briefings\ValueObjects\BriefingSummary;

/**
 * Collects payload data for the delivery velocity template.
 */
final readonly class DeliveryVelocityTemplateCollector implements BriefingTemplateDataCollector
{
    /**
     * Create a new delivery velocity collector instance.
     */
    public function __construct(private StandupUpdateTemplateCollector $standupCollector) {}

    /**
     * {@inheritdoc}
     */
    public function slug(): string
    {
        return 'delivery-velocity';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $data = $this->standupCollector->collect($workspaceId, $dateRange, $parameters);

        /** @var array<string, mixed> $summaryPayload */
        $summaryPayload = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $summary = BriefingSummary::fromArray($summaryPayload);
        $totalDays = $dateRange->days();

        $data['velocity'] = [
            'prs_per_day' => $totalDays > 0 ? round($summary->completed() / $totalDays, 2) : 0,
            'total_days' => $totalDays,
        ];

        return $data;
    }
}
