<?php

declare(strict_types=1);

namespace App\Services\Briefings\Slides;

use App\Services\Briefings\ValueObjects\BriefingAchievements;

/**
 * Builds normalized list items for the highlights slide.
 */
final class BriefingHighlightsItemBuilder
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function build(array $payload, BriefingAchievements $achievements): array
    {
        $items = [];

        if (! $achievements->isEmpty()) {
            foreach ($achievements->items as $achievement) {
                $items[] = sprintf('%s - %s', $achievement->title, $achievement->description);
            }
        }

        $topContributor = $payload['top_contributor'] ?? null;
        if (is_array($topContributor)) {
            $name = isset($topContributor['name']) ? mb_trim((string) $topContributor['name']) : '';
            $prCount = isset($topContributor['pr_count']) ? (int) $topContributor['pr_count'] : 0;
            if ($name !== '' && $prCount > 0) {
                $items[] = sprintf('Top contributor: %s (%d PRs)', $name, $prCount);
            }
        }

        $runs = $payload['runs'] ?? null;
        if (is_array($runs)) {
            foreach (array_slice($runs, 0, 4) as $run) {
                if (! is_array($run)) {
                    continue;
                }

                $prNumber = $run['pr_number'] ?? null;
                $title = isset($run['pr_title']) ? mb_trim((string) $run['pr_title']) : '';
                $status = $run['status'] ?? null;
                $id = $run['id'] ?? null;
                if ($prNumber === null) {
                    continue;
                }

                if ($title === '') {
                    continue;
                }

                if ($status === null) {
                    continue;
                }

                if ($id === null) {
                    continue;
                }

                $items[] = sprintf('PR #%s - %s (%s) [Run %s]', $prNumber, $title, $status, $id);
            }
        }

        return $items;
    }
}
