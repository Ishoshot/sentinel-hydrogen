<?php

declare(strict_types=1);

use App\Enums\Reviews\FindingCategory;
use App\Enums\Reviews\RunStatus;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Plan;
use App\Models\Repository;
use App\Models\Run;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Briefings\BriefingDataCollectorService;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;

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
        BriefingParameters::fromArray([
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->toDateString(),
        ])
    );

    $payload = $data->toArray();

    expect($payload)->toHaveKey('period')
        ->and($payload)->toHaveKey('summary')
        ->and($payload['summary']['total_runs'])->toBe(5)
        ->and($payload['summary']['completed'])->toBe(3)
        ->and($payload['summary']['in_progress'])->toBe(1)
        ->and($payload['summary']['failed'])->toBe(1)
        ->and($payload)->toHaveKey('data_quality')
        ->and($payload)->toHaveKey('evidence');
});

it('collects team summary data with repositories', function (): void {
    Repository::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $data = $this->collector->collect(
        $this->workspace->id,
        'weekly-team-summary',
        BriefingParameters::fromArray([])
    );

    $payload = $data->toArray();

    expect($payload)->toHaveKey('repositories')
        ->and($payload['summary'])->toHaveKey('repository_count')
        ->and($payload['summary']['repository_count'])->toBe(3); // Including the one from beforeEach
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
        BriefingParameters::fromArray([
            'start_date' => now()->subDays(6)->toDateString(),
            'end_date' => now()->toDateString(),
        ])
    );

    $payload = $data->toArray();

    expect($payload)->toHaveKey('velocity')
        ->and($payload['velocity'])->toHaveKey('prs_per_day')
        ->and($payload['velocity'])->toHaveKey('total_days')
        ->and($payload['velocity']['total_days'])->toBe(7);
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
        BriefingParameters::fromArray([
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->toDateString(),
        ])
    );

    $payload = $data->toArray();

    expect($payload)->toHaveKey('engineers')
        ->and($payload)->toHaveKey('top_contributor')
        ->and($payload['top_contributor']['name'])->toBe('john-doe')
        ->and($payload['top_contributor']['pr_count'])->toBe(5);
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
        BriefingParameters::fromArray([
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->toDateString(),
        ])
    );

    $payload = $data->toArray();

    expect($payload)->toHaveKey('code_health')
        ->and($payload['code_health']['total_findings'])->toBe(10)
        ->and($payload['code_health']['critical_issues'])->toBe(2)
        ->and($payload['code_health']['high_issues'])->toBe(3)
        ->and($payload['code_health']['medium_issues'])->toBe(5)
        ->and($payload['code_health'])->toHaveKey('severity_breakdown')
        ->and($payload['code_health'])->toHaveKey('category_breakdown')
        ->and($payload['code_health'])->toHaveKey('top_critical_findings')
        ->and($payload['evidence']['finding_ids'])->toHaveCount(5);
});

it('detects PR milestones in achievements', function (): void {
    $structuredData = BriefingStructuredData::fromArray([
        'summary' => ['prs_merged' => 100],
        'data_quality' => [],
        'evidence' => [],
    ]);

    $achievements = $this->collector->detectAchievements($structuredData)->toArray();

    expect($achievements)->toHaveCount(1)
        ->and($achievements[0]['type'])->toBe('milestone')
        ->and($achievements[0]['title'])->toBe('Century Club');
});

it('detects review coverage achievements', function (): void {
    $structuredData = BriefingStructuredData::fromArray([
        'summary' => ['review_coverage' => 98.5],
        'data_quality' => [],
        'evidence' => [],
    ]);

    $achievements = $this->collector->detectAchievements($structuredData)->toArray();

    expect($achievements)->toHaveCount(1)
        ->and($achievements[0]['title'])->toBe('Full Coverage');
});

it('detects activity streak achievements', function (): void {
    $structuredData = BriefingStructuredData::fromArray([
        'summary' => ['active_days' => 14],
        'data_quality' => [],
        'evidence' => [],
    ]);

    $achievements = $this->collector->detectAchievements($structuredData)->toArray();

    expect($achievements)->toHaveCount(1)
        ->and($achievements[0]['type'])->toBe('streak')
        ->and($achievements[0]['title'])->toBe('Two Week Streak');
});

it('detects top contributor achievements', function (): void {
    $structuredData = BriefingStructuredData::fromArray([
        'top_contributor' => [
            'name' => 'Jane Doe',
            'pr_count' => 15,
        ],
        'data_quality' => [],
        'evidence' => [],
    ]);

    $achievements = $this->collector->detectAchievements($structuredData)->toArray();

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
        BriefingParameters::fromArray([
            'repository_ids' => [$this->repository->id],
        ])
    );

    $payload = $data->toArray();

    expect($payload['summary']['total_runs'])->toBe(3);
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

    $data = $this->collector->collect(
        $this->workspace->id,
        'standup-update',
        BriefingParameters::fromArray([])
    );

    $payload = $data->toArray();

    expect($payload['summary']['total_runs'])->toBe(2);
});

it('throws exception for unknown briefing types', function (): void {
    $this->collector->collect(
        $this->workspace->id,
        'unknown-briefing',
        BriefingParameters::fromArray([])
    );
})->throws(RuntimeException::class, 'Unsupported briefing slug: unknown-briefing');
