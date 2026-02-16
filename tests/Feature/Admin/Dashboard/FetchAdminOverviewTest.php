<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminOverview;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Billing\PlanTier;
use App\Enums\Reviews\RunStatus;
use App\Models\AiOption;
use App\Models\Briefing;
use App\Models\Plan;
use App\Models\Promotion;
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

it('returns a scoped overview for workspace, date range, and run status', function (): void {
    $plan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);

    $workspaceA = Workspace::factory()->create(['plan_id' => $plan->id]);
    $workspaceB = Workspace::factory()->create(['plan_id' => $plan->id]);

    $repositoryA = Repository::factory()->create(['workspace_id' => $workspaceA->id]);
    $repositoryB = Repository::factory()->create(['workspace_id' => $workspaceB->id]);

    Run::factory()->forRepository($repositoryA)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-10 08:00:00',
    ])->create();
    Run::factory()->forRepository($repositoryA)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-11 08:00:00',
    ])->create();
    Run::factory()->forRepository($repositoryA)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-12 08:00:00',
    ])->create();
    Run::factory()->forRepository($repositoryA)->state([
        'status' => RunStatus::Failed,
        'created_at' => '2026-02-11 09:00:00',
    ])->create();
    Run::factory()->forRepository($repositoryA)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-08 08:00:00',
    ])->create();

    Run::factory()->forRepository($repositoryB)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-11 11:00:00',
    ])->create();

    Promotion::factory()->count(2)->create(['is_active' => true]);
    Promotion::factory()->create(['is_active' => false]);

    Briefing::factory()->create(['workspace_id' => null, 'is_active' => true]);
    Briefing::factory()->forWorkspace($workspaceA)->create(['is_active' => true]);
    Briefing::factory()->forWorkspace($workspaceB)->create(['is_active' => true]);

    AiOption::factory()->count(3)->create(['is_active' => true]);
    AiOption::factory()->create(['is_active' => false]);

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspaceA->id,
        'run_status' => RunStatus::Completed->value,
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-12',
    ]);

    $overview = app(FetchAdminOverview::class)->handle($filters);

    expect($overview['workspace_count'])->toBe(1)
        ->and($overview['runs_current_period'])->toBe(3)
        ->and($overview['runs_previous_period'])->toBe(1)
        ->and($overview['run_delta'])->toBe(2)
        ->and($overview['active_promotions'])->toBe(2)
        ->and($overview['active_briefings'])->toBe(2)
        ->and($overview['active_ai_models'])->toBe(3);
});

it('applies plan tier filtering to workspace and run counts', function (): void {
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
    Run::factory()->forRepository($sanctumRepository)->count(2)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-15 08:00:00',
    ])->create();

    $filters = AdminDashboardFilters::fromArray([
        'plan_tier' => PlanTier::Sanctum->value,
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-16',
    ]);

    $overview = app(FetchAdminOverview::class)->handle($filters);

    expect($overview['workspace_count'])->toBe(1)
        ->and($overview['runs_current_period'])->toBe(2);
});

it('returns cached values during ttl window', function (): void {
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

    $action = app(FetchAdminOverview::class);
    $first = $action->handle($filters);

    Run::factory()->forRepository($repository)->state([
        'status' => RunStatus::Completed,
        'created_at' => '2026-02-16 08:30:00',
    ])->create();

    $second = $action->handle($filters);

    expect($first['runs_current_period'])->toBe(1)
        ->and($second['runs_current_period'])->toBe(1);
});
