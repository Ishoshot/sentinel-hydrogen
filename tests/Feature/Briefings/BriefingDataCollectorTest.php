<?php

declare(strict_types=1);

use App\Enums\FindingCategory;
use App\Enums\RunStatus;
use App\Enums\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Plan;
use App\Models\Repository;
use App\Models\Run;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Briefings\BriefingDataCollectorService;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->plan = Plan::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'plan_id' => $this->plan->id,
    ]);

    $this->repository = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->collector = app(BriefingDataCollectorService::class);
});

it('collects standup data with runs summary', function (): void {
    // Create runs in different states
    Run::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(3),
    ]);

    Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'status' => RunStatus::InProgress,
        'created_at' => now()->subDays(2),
    ]);

    Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'status' => RunStatus::Failed,
        'created_at' => now()->subDay(),
    ]);

    $data = $this->collector->collect(
        $this->workspace->id,
        'standup-update',
        [
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->toDateString(),
        ]
    );

    expect($data)->toHaveKey('period')
        ->and($data)->toHaveKey('summary')
        ->and($data['summary']['total_runs'])->toBe(5)
        ->and($data['summary']['completed'])->toBe(3)
        ->and($data['summary']['in_progress'])->toBe(1)
        ->and($data['summary']['failed'])->toBe(1);
});

it('collects team summary data with repositories', function (): void {
    Repository::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $data = $this->collector->collect(
        $this->workspace->id,
        'weekly-team-summary',
        []
    );

    expect($data)->toHaveKey('repositories')
        ->and($data['summary'])->toHaveKey('repository_count')
        ->and($data['summary']['repository_count'])->toBe(3); // Including the one from beforeEach
});

it('collects velocity data with calculations', function (): void {
    Run::factory()->count(7)->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(3),
    ]);

    $data = $this->collector->collect(
        $this->workspace->id,
        'delivery-velocity',
        [
            'start_date' => now()->subDays(6)->toDateString(),
            'end_date' => now()->toDateString(),
        ]
    );

    expect($data)->toHaveKey('velocity')
        ->and($data['velocity'])->toHaveKey('prs_per_day')
        ->and($data['velocity'])->toHaveKey('total_days')
        ->and($data['velocity']['total_days'])->toBe(7);
});

it('collects engineer spotlight data with contributors', function (): void {
    Run::factory()->count(5)->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'status' => RunStatus::Completed,
        'metadata' => ['author' => 'john-doe'],
        'created_at' => now()->subDays(2),
    ]);

    Run::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'status' => RunStatus::Completed,
        'metadata' => ['author' => 'jane-doe'],
        'created_at' => now()->subDays(2),
    ]);

    $data = $this->collector->collect(
        $this->workspace->id,
        'engineer-spotlight',
        [
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->toDateString(),
        ]
    );

    expect($data)->toHaveKey('engineers')
        ->and($data)->toHaveKey('top_contributor')
        ->and($data['top_contributor']['name'])->toBe('john-doe')
        ->and($data['top_contributor']['pr_count'])->toBe(5);
});

it('collects code health data with findings', function (): void {
    $run = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(2),
    ]);

    // Create findings with different severities
    Finding::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'run_id' => $run->id,
        'severity' => SentinelConfigSeverity::Critical,
        'category' => FindingCategory::Security,
        'created_at' => now()->subDays(2),
    ]);

    Finding::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'run_id' => $run->id,
        'severity' => SentinelConfigSeverity::High,
        'category' => FindingCategory::Correctness,
        'created_at' => now()->subDays(2),
    ]);

    Finding::factory()->count(5)->create([
        'workspace_id' => $this->workspace->id,
        'run_id' => $run->id,
        'severity' => SentinelConfigSeverity::Medium,
        'category' => FindingCategory::Performance,
        'created_at' => now()->subDays(2),
    ]);

    $data = $this->collector->collect(
        $this->workspace->id,
        'code-health',
        [
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->toDateString(),
        ]
    );

    expect($data)->toHaveKey('code_health')
        ->and($data['code_health']['total_findings'])->toBe(10)
        ->and($data['code_health']['critical_issues'])->toBe(2)
        ->and($data['code_health']['high_issues'])->toBe(3)
        ->and($data['code_health']['medium_issues'])->toBe(5)
        ->and($data['code_health'])->toHaveKey('severity_breakdown')
        ->and($data['code_health'])->toHaveKey('category_breakdown')
        ->and($data['code_health'])->toHaveKey('top_critical_findings');
});

it('detects PR milestones in achievements', function (): void {
    $structuredData = [
        'summary' => ['prs_merged' => 100],
    ];

    $achievements = $this->collector->detectAchievements($structuredData);

    expect($achievements)->toHaveCount(1)
        ->and($achievements[0]['type'])->toBe('milestone')
        ->and($achievements[0]['title'])->toBe('Century Club');
});

it('detects review coverage achievements', function (): void {
    $structuredData = [
        'summary' => ['review_coverage' => 98.5],
    ];

    $achievements = $this->collector->detectAchievements($structuredData);

    expect($achievements)->toHaveCount(1)
        ->and($achievements[0]['title'])->toBe('Full Coverage');
});

it('detects activity streak achievements', function (): void {
    $structuredData = [
        'summary' => ['active_days' => 14],
    ];

    $achievements = $this->collector->detectAchievements($structuredData);

    expect($achievements)->toHaveCount(1)
        ->and($achievements[0]['type'])->toBe('streak')
        ->and($achievements[0]['title'])->toBe('Two Week Streak');
});

it('detects top contributor achievements', function (): void {
    $structuredData = [
        'top_contributor' => [
            'name' => 'Jane Doe',
            'pr_count' => 15,
        ],
    ];

    $achievements = $this->collector->detectAchievements($structuredData);

    expect($achievements)->toHaveCount(1)
        ->and($achievements[0]['type'])->toBe('personal_best')
        ->and($achievements[0]['title'])->toBe('Star Performer');
});

it('filters runs by repository IDs when provided', function (): void {
    $otherRepo = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    Run::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(2),
    ]);

    Run::factory()->count(5)->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $otherRepo->id,
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(2),
    ]);

    $data = $this->collector->collect(
        $this->workspace->id,
        'standup-update',
        [
            'repository_ids' => [$this->repository->id],
        ]
    );

    expect($data['summary']['total_runs'])->toBe(3);
});

it('uses default date range when not provided', function (): void {
    Run::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(3),
    ]);

    // Run outside default range (more than 7 days ago)
    Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(10),
    ]);

    $data = $this->collector->collect($this->workspace->id, 'standup-update', []);

    expect($data['summary']['total_runs'])->toBe(2);
});

it('handles unknown briefing types with generic collection', function (): void {
    $data = $this->collector->collect($this->workspace->id, 'unknown-briefing', []);

    // Should return standup-like data
    expect($data)->toHaveKey('period')
        ->and($data)->toHaveKey('summary')
        ->and($data)->toHaveKey('runs');
});
