<?php

declare(strict_types=1);

use App\Actions\Analytics\GetRunActivityTimeline;
use App\Enums\Reviews\RunStatus;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns run activity timeline', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    Run::factory()->forRepository($repository)->create(['status' => RunStatus::Completed]);

    $action = new GetRunActivityTimeline;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
});

it('counts total runs correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(3)->forRepository($repository)->create(['status' => RunStatus::Completed]);

    $action = new GetRunActivityTimeline;
    $result = $action->handle($workspace, 30);

    expect($result->first()->count)->toBe(3);
});

it('counts successful runs correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->count(2)->forRepository($repository)->create(['status' => RunStatus::Completed]);
    Run::factory()->forRepository($repository)->create(['status' => RunStatus::Failed]);

    $action = new GetRunActivityTimeline;
    $result = $action->handle($workspace, 30);

    expect($result->first()->successful)->toBe(2);
});

it('counts failed runs correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->create(['status' => RunStatus::Completed]);
    Run::factory()->count(2)->forRepository($repository)->create(['status' => RunStatus::Failed]);

    $action = new GetRunActivityTimeline;
    $result = $action->handle($workspace, 30);

    expect($result->first()->failed)->toBe(2);
});

it('returns empty collection when no runs', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetRunActivityTimeline;
    $result = $action->handle($workspace, 30);

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

    $action = new GetRunActivityTimeline;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
});

it('orders by date', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $run1 = Run::factory()->forRepository($repository)->create(['status' => RunStatus::Completed]);
    $run2 = Run::factory()->forRepository($repository)->create(['status' => RunStatus::Completed]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $run1->id)
        ->update(['created_at' => now()->subDays(2)]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $run2->id)
        ->update(['created_at' => now()->subDays(5)]);

    $action = new GetRunActivityTimeline;
    $result = $action->handle($workspace, 30);

    $dates = $result->pluck('date')->toArray();

    expect($dates[0])->toBeLessThan($dates[1]);
});
