<?php

declare(strict_types=1);

use App\Actions\Analytics\GetRepositoryActivity;
use App\Models\Finding;
use App\Models\Installation;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns repository activity', function (): void {
    $workspace = Workspace::factory()->create();
    $installation = Installation::factory()->create(['workspace_id' => $workspace->id]);
    $repository = Repository::factory()->forInstallation($installation)->create();
    Run::factory()->forRepository($repository)->create();

    $action = new GetRepositoryActivity;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
    expect($result->first())->toHaveKeys([
        'repository_id',
        'repository_name',
        'runs_count',
        'findings_count',
        'last_run_at',
    ]);
});

it('counts runs correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $installation = Installation::factory()->create(['workspace_id' => $workspace->id]);
    $repository = Repository::factory()->forInstallation($installation)->create();

    Run::factory()->count(3)->forRepository($repository)->create();

    $action = new GetRepositoryActivity;
    $result = $action->handle($workspace, 30);

    expect($result->first()['runs_count'])->toBe(3);
});

it('counts findings correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $installation = Installation::factory()->create(['workspace_id' => $workspace->id]);
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->count(5)->forRun($run)->create();

    $action = new GetRepositoryActivity;
    $result = $action->handle($workspace, 30);

    expect($result->first()['findings_count'])->toBe(5);
});

it('returns last run date', function (): void {
    $workspace = Workspace::factory()->create();
    $installation = Installation::factory()->create(['workspace_id' => $workspace->id]);
    $repository = Repository::factory()->forInstallation($installation)->create();

    Run::factory()->forRepository($repository)->create();

    $action = new GetRepositoryActivity;
    $result = $action->handle($workspace, 30);

    expect($result->first()['last_run_at'])->not->toBeNull();
});

it('orders by runs count descending', function (): void {
    $workspace = Workspace::factory()->create();
    $installation = Installation::factory()->create(['workspace_id' => $workspace->id]);

    $repo1 = Repository::factory()->forInstallation($installation)->create(['name' => 'repo-1']);
    $repo2 = Repository::factory()->forInstallation($installation)->create(['name' => 'repo-2']);

    Run::factory()->count(2)->forRepository($repo1)->create();
    Run::factory()->count(5)->forRepository($repo2)->create();

    $action = new GetRepositoryActivity;
    $result = $action->handle($workspace, 30);

    expect($result->first()['repository_name'])->toBe('repo-2');
    expect($result->first()['runs_count'])->toBe(5);
});

it('returns empty for workspace with no repositories', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetRepositoryActivity;
    $result = $action->handle($workspace, 30);

    expect($result)->toBeEmpty();
});

it('respects days parameter for runs', function (): void {
    $workspace = Workspace::factory()->create();
    $installation = Installation::factory()->create(['workspace_id' => $workspace->id]);
    $repository = Repository::factory()->forInstallation($installation)->create();

    $recentRun = Run::factory()->forRepository($repository)->create();
    $oldRun = Run::factory()->forRepository($repository)->create();

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $recentRun->id)
        ->update(['created_at' => now()->subDays(5)]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $oldRun->id)
        ->update(['created_at' => now()->subDays(40)]);

    $action = new GetRepositoryActivity;
    $result = $action->handle($workspace, 30);

    expect($result->first()['runs_count'])->toBe(1);
});

it('only includes repositories for the specified workspace', function (): void {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();

    $installation1 = Installation::factory()->create(['workspace_id' => $workspace1->id]);
    $installation2 = Installation::factory()->create(['workspace_id' => $workspace2->id]);

    $repo1 = Repository::factory()->forInstallation($installation1)->create();
    $repo2 = Repository::factory()->forInstallation($installation2)->create();

    Run::factory()->forRepository($repo1)->create();
    Run::factory()->forRepository($repo2)->create();

    $action = new GetRepositoryActivity;
    $result = $action->handle($workspace1, 30);

    expect($result)->toHaveCount(1);
    expect($result->first()['repository_id'])->toBe($repo1->id);
});
