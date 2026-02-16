<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminRunStatusComposition;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Reviews\RunStatus;
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

it('returns run status totals in enum order for the current filter scope', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-12 09:00:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-12 10:00:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Failed,
        'created_at' => '2026-02-13 09:00:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Queued,
        'created_at' => '2026-02-13 10:00:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Skipped,
        'created_at' => '2026-02-01 10:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'start_date' => '2026-02-12',
        'end_date' => '2026-02-13',
    ]);

    $data = app(FetchAdminRunStatusComposition::class)->handle($filters);

    expect($data['labels'])->toBe([
        'Queued',
        'In Progress',
        'Completed',
        'Failed',
        'Skipped',
    ])->and($data['datasets'][0]['data'])->toBe([1, 0, 2, 1, 0]);
});

it('returns cached run status data during ttl', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-16 08:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'start_date' => '2026-02-16',
        'end_date' => '2026-02-16',
    ]);

    $action = app(FetchAdminRunStatusComposition::class);
    $first = $action->handle($filters);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-16 09:00:00',
    ])->create();

    $second = $action->handle($filters);

    expect($first['datasets'][0]['data'])->toBe([0, 0, 1, 0, 0])
        ->and($second['datasets'][0]['data'])->toBe([0, 0, 1, 0, 0]);
});
