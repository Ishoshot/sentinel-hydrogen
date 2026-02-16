<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminWorkspaceLeaderboard;
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

it('returns workspace leaderboard rows ordered by run volume', function (): void {
    $workspaceA = Workspace::factory()->create(['name' => 'Atlas']);
    $workspaceB = Workspace::factory()->create(['name' => 'Beacon']);

    $repositoryA = Repository::factory()->create(['workspace_id' => $workspaceA->id]);
    $repositoryB = Repository::factory()->create(['workspace_id' => $workspaceB->id]);

    Run::factory()->forRepository($repositoryA)->count(2)->state([
        'status' => RunStatus::Completed,
        'duration_seconds' => 30,
        'created_at' => '2026-02-15 09:00:00',
    ])->create();

    Run::factory()->forRepository($repositoryA)->state([
        'status' => RunStatus::Failed,
        'duration_seconds' => 90,
        'created_at' => '2026-02-15 11:00:00',
    ])->create();

    Run::factory()->forRepository($repositoryB)->state([
        'status' => RunStatus::Completed,
        'duration_seconds' => 60,
        'created_at' => '2026-02-15 10:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'start_date' => '2026-02-15',
        'end_date' => '2026-02-15',
    ]);

    $rows = app(FetchAdminWorkspaceLeaderboard::class)->handle($filters);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['workspace_name'])->toBe('Atlas')
        ->and($rows[0]['total_runs'])->toBe(3)
        ->and($rows[0]['failed_runs'])->toBe(1)
        ->and($rows[0]['success_rate'])->toBe(66.7)
        ->and($rows[0]['avg_duration_seconds'])->toBe(50);
});

it('applies workspace filtering to leaderboard results', function (): void {
    $workspaceA = Workspace::factory()->create(['name' => 'Atlas']);
    $workspaceB = Workspace::factory()->create(['name' => 'Beacon']);

    $repositoryA = Repository::factory()->create(['workspace_id' => $workspaceA->id]);
    $repositoryB = Repository::factory()->create(['workspace_id' => $workspaceB->id]);

    Run::factory()->forRepository($repositoryA)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-16 09:00:00',
    ])->create();

    Run::factory()->forRepository($repositoryB)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-16 09:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspaceA->id,
        'start_date' => '2026-02-16',
        'end_date' => '2026-02-16',
    ]);

    $rows = app(FetchAdminWorkspaceLeaderboard::class)->handle($filters);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['workspace_name'])->toBe('Atlas');
});
