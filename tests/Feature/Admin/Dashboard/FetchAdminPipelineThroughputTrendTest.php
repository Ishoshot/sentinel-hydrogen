<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminPipelineThroughputTrend;
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

it('returns throughput datasets for runs commands and briefings', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $briefing = Briefing::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-14 09:00:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Failed,
        'created_at' => '2026-02-15 09:00:00',
    ])->create();

    CommandRun::factory()->forRepository($repository)->state([
        'status' => CommandRunStatus::Completed,
        'created_at' => '2026-02-14 10:00:00',
    ])->create();

    BriefingGeneration::factory()->forWorkspace($workspace)->forBriefing($briefing)->state([
        'status' => BriefingGenerationStatus::Completed,
        'created_at' => '2026-02-15 11:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'start_date' => '2026-02-14',
        'end_date' => '2026-02-15',
    ]);

    $trend = app(FetchAdminPipelineThroughputTrend::class)->handle($filters);

    expect($trend['labels'])->toBe(['Feb 14', 'Feb 15'])
        ->and($trend['datasets'][0]['data'])->toBe([1, 1])
        ->and($trend['datasets'][1]['data'])->toBe([1, 0])
        ->and($trend['datasets'][2]['data'])->toBe([0, 1]);
});

it('maps unsupported run status filters to empty command and briefing data', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Skipped,
        'created_at' => '2026-02-16 08:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'run_status' => RunStatus::Skipped->value,
        'start_date' => '2026-02-16',
        'end_date' => '2026-02-16',
    ]);

    $trend = app(FetchAdminPipelineThroughputTrend::class)->handle($filters);

    expect($trend['datasets'][0]['data'])->toBe([1])
        ->and($trend['datasets'][1]['data'])->toBe([0])
        ->and($trend['datasets'][2]['data'])->toBe([0]);
});
