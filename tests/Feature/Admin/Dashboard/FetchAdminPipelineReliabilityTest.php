<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminPipelineReliability;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Enums\Commands\CommandRunStatus;
use App\Enums\Reviews\RunStatus;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\CommandRun;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-02-16 10:00:00');
    Cache::flush();
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('returns reliability rates and run latency metrics', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $briefing = Briefing::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'duration_seconds' => 10,
        'created_at' => '2026-02-16 08:00:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'duration_seconds' => 30,
        'created_at' => '2026-02-16 08:10:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Failed,
        'duration_seconds' => 50,
        'created_at' => '2026-02-16 08:20:00',
    ])->create();

    CommandRun::factory()->forRepository($repository)->state([
        'status' => CommandRunStatus::Completed,
        'created_at' => '2026-02-16 08:30:00',
    ])->create();

    CommandRun::factory()->forRepository($repository)->state([
        'status' => CommandRunStatus::Failed,
        'created_at' => '2026-02-16 08:35:00',
    ])->create();

    BriefingGeneration::factory()->forWorkspace($workspace)->forBriefing($briefing)->state([
        'status' => BriefingGenerationStatus::Completed,
        'created_at' => '2026-02-16 08:40:00',
    ])->create();

    BriefingGeneration::factory()->forWorkspace($workspace)->forBriefing($briefing)->state([
        'status' => BriefingGenerationStatus::Failed,
        'created_at' => '2026-02-16 08:45:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'start_date' => '2026-02-16',
        'end_date' => '2026-02-16',
    ]);

    $metrics = app(FetchAdminPipelineReliability::class)->handle($filters);

    expect($metrics['run_success_rate'])->toBe(66.7)
        ->and($metrics['command_success_rate'])->toBe(50.0)
        ->and($metrics['briefing_success_rate'])->toBe(50.0)
        ->and($metrics['avg_run_duration_seconds'])->toBe(30)
        ->and($metrics['p95_run_duration_seconds'])->toBe(50)
        ->and($metrics['total_pipeline_events'])->toBe(7);
});

it('applies workspace scope to reliability totals', function (): void {
    $workspaceA = Workspace::factory()->create();
    $workspaceB = Workspace::factory()->create();

    $repositoryA = Repository::factory()->create(['workspace_id' => $workspaceA->id]);
    $repositoryB = Repository::factory()->create(['workspace_id' => $workspaceB->id]);

    Run::factory()->forRepository($repositoryA)->state([
        'status' => RunStatus::Completed,
        'duration_seconds' => 15,
        'created_at' => '2026-02-16 08:00:00',
    ])->create();

    Run::factory()->forRepository($repositoryB)->state([
        'status' => RunStatus::Failed,
        'duration_seconds' => 60,
        'created_at' => '2026-02-16 08:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspaceA->id,
        'start_date' => '2026-02-16',
        'end_date' => '2026-02-16',
    ]);

    $metrics = app(FetchAdminPipelineReliability::class)->handle($filters);

    expect($metrics['run_success_rate'])->toBe(100.0)
        ->and($metrics['total_pipeline_events'])->toBe(1);
});
