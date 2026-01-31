<?php

declare(strict_types=1);

use App\Actions\Analytics\GetReviewDurationTrends;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns review duration trends by date', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    Run::factory()->forRepository($repository)->create([
        'duration_seconds' => 120,
    ]);

    $action = new GetReviewDurationTrends;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
    expect($result->first())->toHaveKeys(['date', 'avg_duration', 'min_duration', 'max_duration']);
});

it('calculates avg min max duration correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->create(['duration_seconds' => 60]);
    Run::factory()->forRepository($repository)->create(['duration_seconds' => 120]);
    Run::factory()->forRepository($repository)->create(['duration_seconds' => 180]);

    $action = new GetReviewDurationTrends;
    $result = $action->handle($workspace, 30);

    expect($result->first()['avg_duration'])->toBe(120);
    expect($result->first()['min_duration'])->toBe(60);
    expect($result->first()['max_duration'])->toBe(180);
});

it('excludes runs without duration', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->create(['duration_seconds' => 100]);
    Run::factory()->forRepository($repository)->create(['duration_seconds' => null]);

    $action = new GetReviewDurationTrends;
    $result = $action->handle($workspace, 30);

    expect($result->first()['avg_duration'])->toBe(100);
});

it('returns empty collection when no runs with duration', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    Run::factory()->forRepository($repository)->create(['duration_seconds' => null]);

    $action = new GetReviewDurationTrends;
    $result = $action->handle($workspace, 30);

    expect($result)->toBeEmpty();
});

it('respects days parameter', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $recentRun = Run::factory()->forRepository($repository)->create(['duration_seconds' => 100]);
    $oldRun = Run::factory()->forRepository($repository)->create(['duration_seconds' => 200]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $recentRun->id)
        ->update(['created_at' => now()->subDays(5)]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $oldRun->id)
        ->update(['created_at' => now()->subDays(40)]);

    $action = new GetReviewDurationTrends;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
    expect($result->first()['avg_duration'])->toBe(100);
});

it('orders results by date', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $run1 = Run::factory()->forRepository($repository)->create(['duration_seconds' => 100]);
    $run2 = Run::factory()->forRepository($repository)->create(['duration_seconds' => 200]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $run1->id)
        ->update(['created_at' => now()->subDays(2)]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $run2->id)
        ->update(['created_at' => now()->subDays(5)]);

    $action = new GetReviewDurationTrends;
    $result = $action->handle($workspace, 30);

    $dates = $result->pluck('date')->toArray();

    expect($dates[0])->toBeLessThan($dates[1]);
});
