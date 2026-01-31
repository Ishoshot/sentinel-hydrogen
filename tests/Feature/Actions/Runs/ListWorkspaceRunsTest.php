<?php

declare(strict_types=1);

use App\Actions\Runs\ListWorkspaceRuns;
use App\Enums\Reviews\RunStatus;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

it('returns flat paginated runs', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(5)->forRepository($repository)->create();

    $action = new ListWorkspaceRuns;
    $result = $action->flat($workspace, [], 'created_at', 'desc', 20);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result->total())->toBe(5);
});

it('filters runs by status', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(3)->forRepository($repository)->create(['status' => RunStatus::Completed]);
    Run::factory()->count(2)->forRepository($repository)->create(['status' => RunStatus::Failed]);

    $action = new ListWorkspaceRuns;
    $result = $action->flat($workspace, ['status' => RunStatus::Completed], 'created_at', 'desc', 20);

    expect($result->total())->toBe(3);
});

it('filters runs by repository_id', function (): void {
    $workspace = Workspace::factory()->create();
    $repo1 = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $repo2 = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(3)->forRepository($repo1)->create();
    Run::factory()->count(2)->forRepository($repo2)->create();

    $action = new ListWorkspaceRuns;
    $result = $action->flat($workspace, ['repository_id' => $repo1->id], 'created_at', 'desc', 20);

    expect($result->total())->toBe(3);
});

it('filters runs by date range', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $recentRun = Run::factory()->forRepository($repository)->create();
    $oldRun = Run::factory()->forRepository($repository)->create();

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $recentRun->id)
        ->update(['created_at' => now()]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $oldRun->id)
        ->update(['created_at' => now()->subDays(10)]);

    $action = new ListWorkspaceRuns;
    $result = $action->flat($workspace, ['from_date' => now()->subDays(5)->toDateString()], 'created_at', 'desc', 20);

    expect($result->total())->toBe(1);
});

it('includes repository relationship', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    Run::factory()->forRepository($repository)->create();

    $action = new ListWorkspaceRuns;
    $result = $action->flat($workspace, [], 'created_at', 'desc', 20);

    expect($result->first()->relationLoaded('repository'))->toBeTrue();
});

it('includes findings count', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->count(3)->forRun($run)->create();

    $action = new ListWorkspaceRuns;
    $result = $action->flat($workspace, [], 'created_at', 'desc', 20);

    expect($result->first()->findings_count)->toBe(3);
});

it('sorts by created_at ascending', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $oldRun = Run::factory()->forRepository($repository)->create();
    $newRun = Run::factory()->forRepository($repository)->create();

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $oldRun->id)
        ->update(['created_at' => now()->subDay()]);

    $action = new ListWorkspaceRuns;
    $result = $action->flat($workspace, [], 'created_at', 'asc', 20);

    expect($result->first()->id)->toBe($oldRun->id);
});

it('returns empty paginator when no runs', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new ListWorkspaceRuns;
    $result = $action->flat($workspace, [], 'created_at', 'desc', 20);

    expect($result->total())->toBe(0);
});

it('only returns runs for the specified workspace', function (): void {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();

    $repo1 = Repository::factory()->create(['workspace_id' => $workspace1->id]);
    $repo2 = Repository::factory()->create(['workspace_id' => $workspace2->id]);

    Run::factory()->count(3)->forRepository($repo1)->create();
    Run::factory()->count(2)->forRepository($repo2)->create();

    $action = new ListWorkspaceRuns;
    $result = $action->flat($workspace1, [], 'created_at', 'desc', 20);

    expect($result->total())->toBe(3);
});
