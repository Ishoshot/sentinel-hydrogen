<?php

declare(strict_types=1);

use App\Models\Briefing;
use App\Services\Briefings\BriefingSlidesBuilderService;
use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;

it('builds a structured slide deck from briefing data', function (): void {
    $briefing = Briefing::factory()->make([
        'title' => 'Weekly Team Summary',
        'slug' => 'weekly-team-summary',
        'description' => 'Weekly highlights and delivery trends.',
    ]);

    $structuredData = BriefingStructuredData::fromArray([
        'period' => ['start' => '2025-01-01', 'end' => '2025-01-07'],
        'summary' => [
            'total_runs' => 12,
            'completed' => 9,
            'in_progress' => 2,
            'failed' => 1,
            'active_days' => 5,
            'review_coverage' => 82.5,
            'repository_count' => 3,
        ],
        'code_health' => [
            'total_findings' => 6,
            'critical_issues' => 1,
            'high_issues' => 2,
            'medium_issues' => 3,
            'top_critical_findings' => [
                ['id' => 1, 'title' => 'SQL injection risk', 'file_path' => 'app/Auth.php', 'line_start' => 42],
            ],
        ],
        'runs' => [
            ['id' => 101, 'pr_number' => 44, 'pr_title' => 'Fix auth flow', 'status' => 'completed'],
        ],
        'data_quality' => [
            'is_sparse' => false,
            'total_runs' => 12,
            'active_days' => 5,
            'period_days' => 7,
            'review_coverage' => 82.5,
            'notes' => [],
        ],
        'evidence' => [
            'run_ids' => [101, 102],
            'finding_ids' => [1],
            'repository_names' => ['sentinel-api'],
            'notes' => [],
        ],
    ]);

    $achievements = BriefingAchievements::fromArray([
        ['type' => 'milestone', 'title' => 'Century Club', 'description' => '100 pull requests merged', 'value' => 100],
    ]);

    $builder = app(BriefingSlidesBuilderService::class);
    $deck = $builder->build($briefing, $structuredData, $achievements, 'Summary narrative.');

    $payload = $deck->toArray();

    expect($payload)
        ->toHaveKey('version')
        ->and($payload['title'])->toBe('Weekly Team Summary')
        ->and($payload['slides'])->not->toBeEmpty();
});
