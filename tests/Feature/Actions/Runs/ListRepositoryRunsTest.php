<?php

declare(strict_types=1);

use App\Actions\Runs\ListRepositoryRuns;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

it('returns paginated runs for a repository', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(5)->forRepository($repository)->create();

    $action = new ListRepositoryRuns;
    $result = $action->handle($workspace, $repository, 20);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result->total())->toBe(5);
});

it('only returns runs for the specified repository', function (): void {
    $workspace = Workspace::factory()->create();
    $repo1 = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $repo2 = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(3)->forRepository($repo1)->create();
    Run::factory()->count(2)->forRepository($repo2)->create();

    $action = new ListRepositoryRuns;
    $result = $action->handle($workspace, $repo1, 20);

    expect($result->total())->toBe(3);
});

it('only returns runs for the specified workspace', function (): void {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace1->id]);

    Run::factory()->count(3)->forRepository($repository)->create([
        'workspace_id' => $workspace1->id,
    ]);
    Run::factory()->count(2)->forRepository($repository)->create([
        'workspace_id' => $workspace2->id,
    ]);

    $action = new ListRepositoryRuns;
    $result = $action->handle($workspace1, $repository, 20);

    expect($result->total())->toBe(3);
});

it('orders runs by created_at descending', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $oldRun = Run::factory()->forRepository($repository)->create();
    $newRun = Run::factory()->forRepository($repository)->create();

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $oldRun->id)
        ->update(['created_at' => now()->subDay()]);

    $action = new ListRepositoryRuns;
    $result = $action->handle($workspace, $repository, 20);

    expect($result->first()->id)->toBe($newRun->id);
});

it('respects perPage parameter', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(10)->forRepository($repository)->create();

    $action = new ListRepositoryRuns;
    $result = $action->handle($workspace, $repository, 3);

    expect($result->perPage())->toBe(3);
    expect($result->count())->toBe(3);
    expect($result->total())->toBe(10);
});

it('returns empty paginator when no runs', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $action = new ListRepositoryRuns;
    $result = $action->handle($workspace, $repository, 20);

    expect($result->total())->toBe(0);
    expect($result->isEmpty())->toBeTrue();
});
