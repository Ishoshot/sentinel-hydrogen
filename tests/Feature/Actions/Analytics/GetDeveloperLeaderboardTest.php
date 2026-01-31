<?php

declare(strict_types=1);

use App\Actions\Analytics\GetDeveloperLeaderboard;
use App\Enums\Reviews\RunStatus;
use App\Models\Repository;
use App\Models\Run;
use App\Models\User;
use App\Models\Workspace;

it('returns developer leaderboard', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $user = User::factory()->create();

    Run::factory()->forRepository($repository)->create([
        'initiated_by_id' => $user->id,
        'status' => RunStatus::Completed,
    ]);

    $action = new GetDeveloperLeaderboard;
    $result = $action->handle($workspace, 30, 10);

    expect($result)->toHaveCount(1);
    expect($result->first()['name'])->toBe($user->name);
    expect($result->first()['runs_count'])->toBe(1);
});

it('counts successful runs correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $user = User::factory()->create();

    Run::factory()->count(3)->forRepository($repository)->create([
        'initiated_by_id' => $user->id,
        'status' => RunStatus::Completed,
    ]);
    Run::factory()->forRepository($repository)->create([
        'initiated_by_id' => $user->id,
        'status' => RunStatus::Failed,
    ]);

    $action = new GetDeveloperLeaderboard;
    $result = $action->handle($workspace, 30, 10);

    expect($result->first()['runs_count'])->toBe(4);
    expect($result->first()['successful_runs'])->toBe(3);
});

it('orders by runs count descending', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $user1 = User::factory()->create(['name' => 'User 1']);
    $user2 = User::factory()->create(['name' => 'User 2']);

    Run::factory()->count(2)->forRepository($repository)->create([
        'initiated_by_id' => $user1->id,
        'status' => RunStatus::Completed,
    ]);
    Run::factory()->count(5)->forRepository($repository)->create([
        'initiated_by_id' => $user2->id,
        'status' => RunStatus::Completed,
    ]);

    $action = new GetDeveloperLeaderboard;
    $result = $action->handle($workspace, 30, 10);

    expect($result->first()['name'])->toBe('User 2');
    expect($result->first()['runs_count'])->toBe(5);
});

it('respects limit parameter', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    for ($i = 0; $i < 5; $i++) {
        $user = User::factory()->create();
        Run::factory()->forRepository($repository)->create([
            'initiated_by_id' => $user->id,
            'status' => RunStatus::Completed,
        ]);
    }

    $action = new GetDeveloperLeaderboard;
    $result = $action->handle($workspace, 30, 3);

    expect($result)->toHaveCount(3);
});

it('returns empty collection when no runs with initiated_by', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    // Create run without initiated_by_id (null)
    Run::factory()->forRepository($repository)->create([
        'initiated_by_id' => null,
    ]);

    $action = new GetDeveloperLeaderboard;
    $result = $action->handle($workspace, 30, 10);

    expect($result)->toBeEmpty();
});

it('respects days parameter', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $user = User::factory()->create();

    $recentRun = Run::factory()->forRepository($repository)->create([
        'initiated_by_id' => $user->id,
        'status' => RunStatus::Completed,
    ]);

    $oldRun = Run::factory()->forRepository($repository)->create([
        'initiated_by_id' => $user->id,
        'status' => RunStatus::Completed,
    ]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $recentRun->id)
        ->update(['created_at' => now()->subDays(5)]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $oldRun->id)
        ->update(['created_at' => now()->subDays(40)]);

    $action = new GetDeveloperLeaderboard;
    $result = $action->handle($workspace, 30, 10);

    expect($result->first()['runs_count'])->toBe(1);
});
