<?php

declare(strict_types=1);

namespace App\Services\Briefings\Support;

use App\Models\BriefingGeneration;
use App\Services\Briefings\ValueObjects\BriefingOutputFormats;
use RuntimeException;

final class BriefingOutputFormatResolver
{
    /**
     * Resolve output format configuration for the given generation.
     */
    public function resolve(BriefingGeneration $generation): BriefingOutputFormats
    {
        $briefing = $generation->briefing;
        $formats = is_array($briefing?->output_formats)
            ? array_values($briefing->output_formats)
            : null;

        $resolved = BriefingOutputFormats::fromArray($formats);

        if ($resolved->isEmpty()) {
            throw new RuntimeException('Briefing output formats are not configured.');
        }

        return $resolved;
    }
}
