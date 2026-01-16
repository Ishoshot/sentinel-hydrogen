<?php

declare(strict_types=1);

namespace App\Services\Briefings\Contracts;

interface BriefingDataCollector
{
    /**
     * Collect data for a briefing.
     *
     * @param  int  $workspaceId  The workspace ID to collect data for
     * @param  string  $briefingSlug  The briefing template slug
     * @param  array<string, mixed>  $parameters  User-provided parameters
     * @return array<string, mixed> The collected structured data
     */
    public function collect(int $workspaceId, string $briefingSlug, array $parameters): array;

    /**
     * Detect achievements from the collected data.
     *
     * @param  array<string, mixed>  $structuredData  The collected data
     * @return array<int, array{type: string, title: string, description: string, value?: mixed}> Detected achievements
     */
    public function detectAchievements(array $structuredData): array;
}
