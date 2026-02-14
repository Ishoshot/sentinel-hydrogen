<?php

declare(strict_types=1);

use App\Services\Briefings\Slides\BriefingHighlightsItemBuilder;
use App\Services\Briefings\ValueObjects\BriefingAchievements;

it('builds highlights from achievements, contributor and runs', function (): void {
    $builder = new BriefingHighlightsItemBuilder;

    $achievements = BriefingAchievements::fromArray([
        ['type' => 'milestone', 'title' => 'Century Club', 'description' => '100 pull requests merged', 'value' => 100],
    ]);

    $items = $builder->build([
        'top_contributor' => ['name' => 'Jane', 'pr_count' => 7],
        'runs' => [
            ['id' => 101, 'pr_number' => 12, 'pr_title' => 'Refactor worker', 'status' => 'completed'],
            ['id' => 102, 'pr_number' => 13, 'pr_title' => 'Improve tests', 'status' => 'completed'],
        ],
    ], $achievements);

    expect($items)->toContain('Century Club - 100 pull requests merged')
        ->and($items)->toContain('Top contributor: Jane (7 PRs)')
        ->and($items)->toContain('PR #12 - Refactor worker (completed) [Run 101]');
});

it('skips malformed run entries', function (): void {
    $builder = new BriefingHighlightsItemBuilder;

    $items = $builder->build([
        'runs' => [
            ['id' => 1, 'pr_number' => 10, 'pr_title' => '', 'status' => 'completed'],
            ['id' => 2, 'pr_title' => 'Missing pr number', 'status' => 'completed'],
        ],
    ], BriefingAchievements::fromArray([]));

    expect($items)->toBeEmpty();
});
