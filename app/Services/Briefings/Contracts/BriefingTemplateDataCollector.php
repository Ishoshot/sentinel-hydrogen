<?php

declare(strict_types=1);

namespace App\Services\Briefings\Contracts;

use App\Services\Briefings\ValueObjects\BriefingDateRange;

/**
 * Contract for collecting structured payloads for a specific briefing template.
 */
interface BriefingTemplateDataCollector
{
    /**
     * The briefing template slug handled by this collector.
     */
    public function slug(): string;

    /**
     * Collect structured data for a briefing template.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function collect(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array;
}
