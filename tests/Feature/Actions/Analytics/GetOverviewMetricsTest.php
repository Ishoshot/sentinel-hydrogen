<?php

declare(strict_types=1);

use App\Actions\Analytics\GetOverviewMetrics;
use App\Models\Finding;
use App\Models\Installation;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns total runs count', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    Run::factory()->count(5)->forRepository($repository)->create();

    $action = new GetOverviewMetrics;
    $result = $action->handle($workspace);

    expect($result['total_runs'])->toBe(5);
});

it('returns total findings count', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();
    Finding::factory()->count(3)->forRun($run)->create();

    $action = new GetOverviewMetrics;
    $result = $action->handle($workspace);

    expect($result['total_findings'])->toBe(3);
});

it('returns average duration seconds', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    Run::factory()->forRepository($repository)->create(['duration_seconds' => 100]);
    Run::factory()->forRepository($repository)->create(['duration_seconds' => 200]);

    $action = new GetOverviewMetrics;
    $result = $action->handle($workspace);

    expect($result['average_duration_seconds'])->toBe(150);
});

it('returns zero average duration when no runs have duration', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetOverviewMetrics;
    $result = $action->handle($workspace);

    expect($result['average_duration_seconds'])->toBe(0);
});

it('returns active repositories count for repos with recent runs', function (): void {
    $workspace = Workspace::factory()->create();

    // Create installation for the workspace (required for repositories() relationship)
    $installation = Installation::factory()->create(['workspace_id' => $workspace->id]);

    // Create repositories through the installation
    $activeRepo = Repository::factory()->forInstallation($installation)->create();
    $inactiveRepo = Repository::factory()->forInstallation($installation)->create();

    // Create recent run for active repo
    $recentRun = Run::factory()->forRepository($activeRepo)->create();

    // Create old run for inactive repo
    $oldRun = Run::factory()->forRepository($inactiveRepo)->create();

    // Use query builder to update created_at without triggering model events
    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $recentRun->id)
        ->update(['created_at' => now()->subDays(5)]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $oldRun->id)
        ->update(['created_at' => now()->subDays(60)]);

    $action = new GetOverviewMetrics;
    $result = $action->handle($workspace);

    expect($result['active_repositories'])->toBe(1);
});

it('returns empty metrics for workspace with no data', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetOverviewMetrics;
    $result = $action->handle($workspace);

    expect($result)->toBe([
        'total_runs' => 0,
        'total_findings' => 0,
        'average_duration_seconds' => 0,
        'active_repositories' => 0,
    ]);
});

it('only counts runs for the specified workspace', function (): void {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();

    $repo1 = Repository::factory()->create(['workspace_id' => $workspace1->id]);
    $repo2 = Repository::factory()->create(['workspace_id' => $workspace2->id]);

    Run::factory()->count(3)->forRepository($repo1)->create();
    Run::factory()->count(5)->forRepository($repo2)->create();

    $action = new GetOverviewMetrics;
    $result = $action->handle($workspace1);

    expect($result['total_runs'])->toBe(3);
});
