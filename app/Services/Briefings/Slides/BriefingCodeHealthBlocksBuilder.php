<?php

declare(strict_types=1);

namespace App\Services\Briefings\Slides;

use App\Services\Briefings\ValueObjects\BriefingSlideBlock;
use App\Services\Briefings\ValueObjects\BriefingSlideMetric;

/**
 * Builds code-health blocks for the highlights deck.
 */
final class BriefingCodeHealthBlocksBuilder
{
    /**
     * @param  array<string, mixed>  $codeHealth
     * @return array<int, BriefingSlideBlock>
     */
    public function build(array $codeHealth): array
    {
        $metrics = [
            new BriefingSlideMetric('Total Findings', (int) ($codeHealth['total_findings'] ?? 0)),
            new BriefingSlideMetric('Critical', (int) ($codeHealth['critical_issues'] ?? 0)),
            new BriefingSlideMetric('High', (int) ($codeHealth['high_issues'] ?? 0)),
            new BriefingSlideMetric('Medium', (int) ($codeHealth['medium_issues'] ?? 0)),
        ];

        $blocks = [BriefingSlideBlock::metrics($metrics)];

        $criticalFindings = $codeHealth['top_critical_findings'] ?? null;
        if (! is_array($criticalFindings) || $criticalFindings === []) {
            return $blocks;
        }

        $items = [];
        foreach (array_slice($criticalFindings, 0, 5) as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            $title = isset($finding['title']) ? mb_trim((string) $finding['title']) : '';
            $filePath = isset($finding['file_path']) ? mb_trim((string) $finding['file_path']) : '';
            $lineStart = $finding['line_start'] ?? null;
            $id = $finding['id'] ?? null;
            if ($title === '') {
                continue;
            }

            if ($filePath === '') {
                continue;
            }

            if ($lineStart === null) {
                continue;
            }

            if ($id === null) {
                continue;
            }

            $items[] = sprintf('%s - %s:%s [Finding %s]', $title, $filePath, $lineStart, $id);
        }

        if ($items !== []) {
            $blocks[] = BriefingSlideBlock::list($items, 'Top Critical Findings');
        }

        return $blocks;
    }
}
