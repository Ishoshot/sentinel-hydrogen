<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Services\Briefings\Contracts\BriefingDataCollector;
use App\Services\Briefings\Resolvers\BriefingTemplateCollectorResolver;
use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingDateRange;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;

/**
 * Orchestrates briefing template collectors and achievement detection.
 */
final readonly class BriefingDataCollectorService implements BriefingDataCollector
{
    /**
     * Create a new data collector orchestrator.
     */
    public function __construct(
        private BriefingTemplateCollectorResolver $templateCollectorResolver,
        private BriefingAchievementDetector $achievementDetector,
    ) {}

    /**
     * Collect data for a briefing.
     */
    public function collect(int $workspaceId, string $briefingSlug, BriefingParameters $parameters): BriefingStructuredData
    {
        $parameterValues = $parameters->toArray();
        $collector = $this->templateCollectorResolver->resolve($briefingSlug);

        return BriefingStructuredData::fromArray(
            $collector->collect(
                $workspaceId,
                BriefingDateRange::fromArray($parameterValues),
                $parameterValues,
            )
        );
    }

    /**
     * Detect achievement milestones based on structured data.
     */
    public function detectAchievements(BriefingStructuredData $structuredData): BriefingAchievements
    {
        return $this->achievementDetector->detect($structuredData);
    }
}
