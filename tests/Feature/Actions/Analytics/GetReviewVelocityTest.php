<?php

declare(strict_types=1);

use App\Actions\Analytics\GetReviewVelocity;
use App\Enums\Reviews\RunStatus;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns review velocity by day', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'duration_seconds' => 120,
    ]);

    $action = new GetReviewVelocity;
    $result = $action->handle($workspace, 30, 'day');

    expect($result)->toHaveCount(1);
    expect($result->first())->toHaveKeys([
        'period',
        'reviews_count',
        'completed_count',
        'avg_duration',
        'active_repositories',
    ]);
});

it('counts reviews and completed correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(2)->forRepository($repository)->create(['status' => RunStatus::Completed]);
    Run::factory()->forRepository($repository)->create(['status' => RunStatus::Failed]);

    $action = new GetReviewVelocity;
    $result = $action->handle($workspace, 30, 'day');

    expect($result->first()['reviews_count'])->toBe(3);
    expect($result->first()['completed_count'])->toBe(2);
});

it('counts active repositories correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repo1 = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $repo2 = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repo1)->create(['status' => RunStatus::Completed]);
    Run::factory()->forRepository($repo2)->create(['status' => RunStatus::Completed]);

    $action = new GetReviewVelocity;
    $result = $action->handle($workspace, 30, 'day');

    expect($result->first()['active_repositories'])->toBe(2);
});

it('calculates average duration', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'duration_seconds' => 60,
    ]);
    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'duration_seconds' => 120,
    ]);

    $action = new GetReviewVelocity;
    $result = $action->handle($workspace, 30, 'day');

    expect($result->first()['avg_duration'])->toBe(90);
});

it('returns empty collection when no runs', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetReviewVelocity;
    $result = $action->handle($workspace, 30, 'day');

    expect($result)->toBeEmpty();
});

it('respects days parameter', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $recentRun = Run::factory()->forRepository($repository)->create(['status' => RunStatus::Completed]);
    $oldRun = Run::factory()->forRepository($repository)->create(['status' => RunStatus::Completed]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $recentRun->id)
        ->update(['created_at' => now()->subDays(5)]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $oldRun->id)
        ->update(['created_at' => now()->subDays(40)]);

    $action = new GetReviewVelocity;
    $result = $action->handle($workspace, 30, 'day');

    expect($result)->toHaveCount(1);
});
