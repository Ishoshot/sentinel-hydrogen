<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminRunsTrend;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Billing\PlanTier;
use App\Enums\Reviews\RunStatus;
use App\Models\Plan;
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

it('returns daily run trend points for the filtered date range', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-10 08:00:00',
    ])->create();
    Run::factory()->forRepository($repository)->count(2)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-12 08:00:00',
    ])->create();
    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Failed,
        'created_at' => '2026-02-13 08:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-13',
    ]);

    $trend = app(FetchAdminRunsTrend::class)->handle($filters);

    expect($trend['labels'])->toBe(['Feb 10', 'Feb 11', 'Feb 12', 'Feb 13'])
        ->and($trend['datasets'][0]['data'])->toBe([1, 0, 2, 1]);
});

it('supports plan tier and run status filters', function (): void {
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $sanctumPlan = Plan::factory()->create(['tier' => PlanTier::Sanctum->value]);

    $foundationWorkspace = Workspace::factory()->create(['plan_id' => $foundationPlan->id]);
    $sanctumWorkspace = Workspace::factory()->create(['plan_id' => $sanctumPlan->id]);

    $foundationRepository = Repository::factory()->create(['workspace_id' => $foundationWorkspace->id]);
    $sanctumRepository = Repository::factory()->create(['workspace_id' => $sanctumWorkspace->id]);

    Run::factory()->forRepository($foundationRepository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-15 08:00:00',
    ])->create();
    Run::factory()->forRepository($sanctumRepository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-15 08:00:00',
    ])->create();
    Run::factory()->forRepository($sanctumRepository)->state([
        'status' => RunStatus::Failed,
        'created_at' => '2026-02-15 08:30:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'plan_tier' => PlanTier::Sanctum->value,
        'run_status' => RunStatus::Completed->value,
        'start_date' => '2026-02-15',
        'end_date' => '2026-02-15',
    ]);

    $trend = app(FetchAdminRunsTrend::class)->handle($filters);

    expect($trend['datasets'][0]['data'])->toBe([1]);
});
