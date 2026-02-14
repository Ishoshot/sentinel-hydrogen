<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Services\Briefings\ValueObjects\Achievement;
use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;
use App\Services\Briefings\ValueObjects\BriefingSummary;
use App\Services\Briefings\ValueObjects\BriefingTopContributor;

/**
 * Detects generated briefing achievements from structured payloads.
 */
final class BriefingAchievementDetector
{
    /**
     * Detect achievements for a structured briefing payload.
     */
    public function detect(BriefingStructuredData $structuredData): BriefingAchievements
    {
        /** @var array<int, Achievement> $achievements */
        $achievements = [];
        $summary = $structuredData->summary();

        $this->detectPrMilestone($achievements, $summary->prsMerged());
        $this->detectReviewCoverage($achievements, $summary);
        $this->detectActivityStreak($achievements, $summary->activeDays());
        $this->detectTopContributor($achievements, $structuredData->topContributor());

        return BriefingAchievements::fromItems($achievements);
    }

    /**
     * @param  array<int, Achievement>  $achievements
     */
    private function detectPrMilestone(array &$achievements, int $prCount): void
    {
        $milestone = match (true) {
            $prCount >= 100 => ['title' => 'Century Club', 'description' => sprintf('%d pull requests merged!', $prCount)],
            $prCount >= 50 => ['title' => 'Halfway There', 'description' => sprintf('%d pull requests merged this period', $prCount)],
            default => null,
        };

        if ($milestone === null) {
            return;
        }

        $achievements[] = new Achievement(
            type: 'milestone',
            title: $milestone['title'],
            description: $milestone['description'],
            value: $prCount,
        );
    }

    /**
     * @param  array<int, Achievement>  $achievements
     */
    private function detectReviewCoverage(array &$achievements, BriefingSummary $summary): void
    {
        $reviewCoverage = $summary->reviewCoverage();

        if ($reviewCoverage < 95) {
            return;
        }

        $achievements[] = new Achievement(
            type: 'milestone',
            title: 'Full Coverage',
            description: sprintf('%.1f%% of PRs reviewed by AI', $reviewCoverage),
            value: $reviewCoverage,
        );
    }

    /**
     * @param  array<int, Achievement>  $achievements
     */
    private function detectActivityStreak(array &$achievements, int $activeDays): void
    {
        $streak = match (true) {
            $activeDays >= 14 => ['title' => 'Two Week Streak', 'description' => sprintf('%d consecutive days of activity', $activeDays)],
            $activeDays >= 7 => ['title' => 'Week Warrior', 'description' => sprintf('%d consecutive days of activity', $activeDays)],
            default => null,
        };

        if ($streak === null) {
            return;
        }

        $achievements[] = new Achievement(
            type: 'streak',
            title: $streak['title'],
            description: $streak['description'],
            value: $activeDays,
        );
    }

    /**
     * @param  array<int, Achievement>  $achievements
     */
    private function detectTopContributor(array &$achievements, ?BriefingTopContributor $topContributor): void
    {
        if (! $topContributor instanceof BriefingTopContributor || $topContributor->prCount < 10) {
            return;
        }

        $achievements[] = new Achievement(
            type: 'personal_best',
            title: 'Star Performer',
            description: sprintf('%s merged %d PRs this period', $topContributor->name, $topContributor->prCount),
            value: $topContributor->prCount,
        );
    }
}
