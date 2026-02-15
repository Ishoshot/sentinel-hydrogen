<?php

declare(strict_types=1);

namespace App\Services\Briefings\Builders;

use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Renders briefing AI prompts from Blade templates.
 */
final class BriefingPromptBuilder
{
    /**
     * Render the prompt for narrative generation.
     */
    public function render(
        string $promptPath,
        BriefingStructuredData $structuredData,
        BriefingAchievements $achievements,
    ): string {
        if (! View::exists($promptPath)) {
            throw new RuntimeException(sprintf('Briefing prompt template not found: %s', $promptPath));
        }

        return View::make($promptPath, [
            'data' => $structuredData->toArray(),
            'achievements' => $achievements->toArray(),
        ])->render();
    }
}
