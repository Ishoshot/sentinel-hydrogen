<?php

declare(strict_types=1);

namespace App\Services\Briefings\Resolvers;

use App\Services\Briefings\Contracts\BriefingTemplateDataCollector;
use RuntimeException;

/**
 * Resolve briefing template collectors by slug.
 */
final class BriefingTemplateCollectorResolver
{
    /**
     * @var array<string, BriefingTemplateDataCollector>
     */
    private array $collectorsBySlug = [];

    /**
     * Create a new resolver instance.
     *
     * @param  iterable<int, BriefingTemplateDataCollector>  $templateCollectors
     */
    public function __construct(iterable $templateCollectors)
    {
        foreach ($templateCollectors as $collector) {
            $slug = $collector->slug();

            if (isset($this->collectorsBySlug[$slug])) {
                throw new RuntimeException(sprintf('Duplicate briefing collector slug: %s', $slug));
            }

            $this->collectorsBySlug[$slug] = $collector;
        }
    }

    /**
     * Resolve the collector for a briefing slug.
     */
    public function resolve(string $briefingSlug): BriefingTemplateDataCollector
    {
        $collector = $this->collectorsBySlug[$briefingSlug] ?? null;

        if ($collector instanceof BriefingTemplateDataCollector) {
            return $collector;
        }

        throw new RuntimeException(sprintf('Unsupported briefing slug: %s', $briefingSlug));
    }
}
