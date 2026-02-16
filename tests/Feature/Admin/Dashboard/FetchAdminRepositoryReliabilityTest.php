<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminRepositoryReliability;
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

it('returns repository reliability rows with score and duration metrics', function (): void {
    $workspace = Workspace::factory()->create();

    $repositoryA = Repository::factory()->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'acme/api',
    ]);

    $repositoryB = Repository::factory()->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'acme/web',
    ]);

    Run::factory()->forRepository($repositoryA)->count(2)->state([
        'status' => RunStatus::Completed,
        'duration_seconds' => 40,
        'created_at' => '2026-02-16 08:00:00',
    ])->create();

    Run::factory()->forRepository($repositoryA)->state([
        'status' => RunStatus::Failed,
        'duration_seconds' => 100,
        'created_at' => '2026-02-16 09:00:00',
    ])->create();

    Run::factory()->forRepository($repositoryB)->state([
        'status' => RunStatus::Completed,
        'duration_seconds' => 20,
        'created_at' => '2026-02-16 08:30:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'start_date' => '2026-02-16',
        'end_date' => '2026-02-16',
    ]);

    $rows = app(FetchAdminRepositoryReliability::class)->handle($filters);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['repository_name'])->toBe('acme/api')
        ->and($rows[0]['total_runs'])->toBe(3)
        ->and($rows[0]['reliability_score'])->toBe(66.7)
        ->and($rows[0]['avg_duration_seconds'])->toBe(60);
});

it('supports run status filter on repository reliability', function (): void {
    $workspace = Workspace::factory()->create();

    $repository = Repository::factory()->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'acme/api',
    ]);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-16 08:00:00',
    ])->create();

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Failed,
        'created_at' => '2026-02-16 09:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'run_status' => RunStatus::Failed->value,
        'start_date' => '2026-02-16',
        'end_date' => '2026-02-16',
    ]);

    $rows = app(FetchAdminRepositoryReliability::class)->handle($filters);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['total_runs'])->toBe(1)
        ->and($rows[0]['reliability_score'])->toBe(0.0);
});
