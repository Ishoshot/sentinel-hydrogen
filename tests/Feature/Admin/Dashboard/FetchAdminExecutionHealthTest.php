<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminExecutionHealth;
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

it('returns execution health metrics for the current filter scope', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $briefing = Briefing::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Queued,
        'created_at' => '2026-02-16 09:40:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Queued,
        'created_at' => '2026-02-16 09:55:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::InProgress,
        'created_at' => '2026-02-16 09:20:00',
        'started_at' => '2026-02-16 09:30:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::InProgress,
        'created_at' => '2026-02-16 09:58:00',
        'started_at' => '2026-02-16 09:58:00',
    ])->create();

    CommandRun::factory()->forRepository($repository)->state([
        'status' => CommandRunStatus::Failed,
        'created_at' => '2026-02-16 09:30:00',
    ])->create();

    BriefingGeneration::factory()->forWorkspace($workspace)->forBriefing($briefing)->state([
        'status' => BriefingGenerationStatus::Failed,
        'created_at' => '2026-02-16 09:35:00',
    ])->create();

    BriefingGeneration::factory()->forWorkspace($workspace)->forBriefing($briefing)->state([
        'status' => BriefingGenerationStatus::Processing,
        'created_at' => '2026-02-16 09:45:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'start_date' => '2026-02-16',
        'end_date' => '2026-02-16',
    ]);

    $health = app(FetchAdminExecutionHealth::class)->handle($filters);

    expect($health['stuck_queued_runs'])->toBe(1)
        ->and($health['long_running_runs'])->toBe(1)
        ->and($health['failed_command_runs'])->toBe(1)
        ->and($health['failed_briefing_generations'])->toBe(1)
        ->and($health['processing_briefings'])->toBe(1);
});

it('applies workspace filters to execution health counts', function (): void {
    $workspaceA = Workspace::factory()->create();
    $workspaceB = Workspace::factory()->create();

    $repositoryA = Repository::factory()->create(['workspace_id' => $workspaceA->id]);
    $repositoryB = Repository::factory()->create(['workspace_id' => $workspaceB->id]);

    Run::factory()->forRepository($repositoryA)->state([
        'status' => RunStatus::Queued,
        'created_at' => '2026-02-16 09:30:00',
    ])->create();

    Run::factory()->forRepository($repositoryB)->state([
        'status' => RunStatus::Queued,
        'created_at' => '2026-02-16 09:30:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspaceA->id,
        'start_date' => '2026-02-16',
        'end_date' => '2026-02-16',
    ]);

    $health = app(FetchAdminExecutionHealth::class)->handle($filters);

    expect($health['stuck_queued_runs'])->toBe(1);
});
