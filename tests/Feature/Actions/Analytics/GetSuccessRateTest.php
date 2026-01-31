<?php

declare(strict_types=1);

use App\Actions\Analytics\GetSuccessRate;
use App\Enums\Reviews\RunStatus;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns success rate grouped by date', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $today = now()->toDateString();

    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'created_at' => now(),
    ]);
    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Failed,
        'created_at' => now(),
    ]);

    $action = new GetSuccessRate;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
    expect($result->first())->toMatchArray([
        'date' => $today,
        'successful' => 1,
        'failed' => 1,
        'total' => 2,
        'success_rate' => 50.0,
    ]);
});

it('returns empty collection when no runs exist', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetSuccessRate;
    $result = $action->handle($workspace, 30);

    expect($result)->toBeEmpty();
});

it('respects days parameter', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    // Create run within range
    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(5),
    ]);

    // Create run outside range
    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(40),
    ]);

    $action = new GetSuccessRate;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
});

it('calculates correct success rate for all completed runs', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(4)->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'created_at' => now(),
    ]);

    $action = new GetSuccessRate;
    $result = $action->handle($workspace, 30);

    expect($result->first()['success_rate'])->toBe(100.0);
});

it('calculates correct success rate for all failed runs', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(3)->forRepository($repository)->create([
        'status' => RunStatus::Failed,
        'created_at' => now(),
    ]);

    $action = new GetSuccessRate;
    $result = $action->handle($workspace, 30);

    expect($result->first()['success_rate'])->toBe(0.0);
});

it('sorts results by date', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(2),
    ]);
    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(5),
    ]);
    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'created_at' => now(),
    ]);

    $action = new GetSuccessRate;
    $result = $action->handle($workspace, 30);

    $dates = $result->pluck('date')->toArray();

    expect($dates)->toBe(array_values(array_unique($dates)));
    expect($dates[0])->toBeLessThanOrEqual($dates[1]);
});

it('only includes runs for the specified workspace', function (): void {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();

    $repo1 = Repository::factory()->create(['workspace_id' => $workspace1->id]);
    $repo2 = Repository::factory()->create(['workspace_id' => $workspace2->id]);

    Run::factory()->forRepository($repo1)->create([
        'status' => RunStatus::Completed,
        'created_at' => now(),
    ]);
    Run::factory()->count(3)->forRepository($repo2)->create([
        'status' => RunStatus::Completed,
        'created_at' => now(),
    ]);

    $action = new GetSuccessRate;
    $result = $action->handle($workspace1, 30);

    expect($result->first()['total'])->toBe(1);
});
