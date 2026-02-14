<?php

declare(strict_types=1);

namespace App\Services\Briefings\Slides;

use App\Services\Briefings\ValueObjects\BriefingSlide;
use App\Services\Briefings\ValueObjects\BriefingSlideBlock;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;

/**
 * Builds diagnostics and traceability slides for a briefing deck.
 */
final class BriefingDiagnosticsSlidesBuilder
{
    /**
     * Build data quality and evidence slides.
     *
     * @return array<int, BriefingSlide>
     */
    public function build(BriefingStructuredData $structuredData): array
    {
        $slides = [];

        $dataQualitySlide = $this->buildDataQualitySlide($structuredData);
        if ($dataQualitySlide instanceof BriefingSlide) {
            $slides[] = $dataQualitySlide;
        }

        $evidenceSlide = $this->buildEvidenceSlide($structuredData);
        if ($evidenceSlide instanceof BriefingSlide) {
            $slides[] = $evidenceSlide;
        }

        return $slides;
    }

    /**
     * Build a data quality slide when gaps exist.
     */
    private function buildDataQualitySlide(BriefingStructuredData $structuredData): ?BriefingSlide
    {
        $dataQuality = $structuredData->dataQuality;
        $notes = $dataQuality->notes;

        if (! $dataQuality->isSparse && $notes === []) {
            return null;
        }

        $items = [];

        if ($dataQuality->isSparse) {
            $items[] = 'Data is sparse for this period; interpret trends cautiously.';
        }

        foreach (array_slice($notes, 0, 5) as $note) {
            $items[] = $note;
        }

        if ($items === []) {
            return null;
        }

        return new BriefingSlide(
            id: 'data-quality',
            type: 'data_quality',
            title: 'Data Quality',
            subtitle: null,
            blocks: [BriefingSlideBlock::list($items)],
        );
    }

    /**
     * Build an evidence slide from the evidence payload.
     */
    private function buildEvidenceSlide(BriefingStructuredData $structuredData): ?BriefingSlide
    {
        $evidence = $structuredData->evidence;
        $items = [];

        if ($evidence->runIds !== []) {
            $items[] = 'Run IDs: '.$this->formatList($evidence->runIds, 10);
        }

        if ($evidence->findingIds !== []) {
            $items[] = 'Finding IDs: '.$this->formatList($evidence->findingIds, 10);
        }

        if ($evidence->repositoryNames !== []) {
            $items[] = 'Repositories: '.$this->formatList($evidence->repositoryNames, 6);
        }

        if ($items === []) {
            return null;
        }

        return new BriefingSlide(
            id: 'evidence',
            type: 'evidence',
            title: 'Evidence',
            subtitle: null,
            blocks: [BriefingSlideBlock::list($items)],
        );
    }

    /**
     * Format list values and indicate omitted remaining items.
     *
     * @param  array<int, int|string>  $items
     */
    private function formatList(array $items, int $limit): string
    {
        $visible = array_slice($items, 0, $limit);
        $formatted = implode(', ', array_map(static fn (int|string $item): string => (string) $item, $visible));

        $remaining = count($items) - count($visible);
        if ($remaining > 0) {
            return sprintf('%s, +%d more', $formatted, $remaining);
        }

        return $formatted;
    }
}
