<?php

declare(strict_types=1);

use App\Actions\Analytics\GetTokenUsage;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns token usage grouped by date', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->create([
        'metrics' => ['input_tokens' => 100, 'output_tokens' => 50],
        'created_at' => now(),
    ]);

    $action = new GetTokenUsage;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
    expect($result->first())->toMatchArray([
        'date' => now()->toDateString(),
        'total_input_tokens' => 100,
        'total_output_tokens' => 50,
        'total_tokens' => 150,
    ]);
});

it('aggregates tokens for same date', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->create([
        'metrics' => ['input_tokens' => 100, 'output_tokens' => 50],
        'created_at' => now(),
    ]);
    Run::factory()->forRepository($repository)->create([
        'metrics' => ['input_tokens' => 200, 'output_tokens' => 100],
        'created_at' => now(),
    ]);

    $action = new GetTokenUsage;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
    expect($result->first()['total_input_tokens'])->toBe(300);
    expect($result->first()['total_output_tokens'])->toBe(150);
    expect($result->first()['total_tokens'])->toBe(450);
});

it('returns empty collection when no runs with metrics', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetTokenUsage;
    $result = $action->handle($workspace, 30);

    expect($result)->toBeEmpty();
});

it('excludes runs without metrics', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->create([
        'metrics' => ['input_tokens' => 100, 'output_tokens' => 50],
        'created_at' => now(),
    ]);
    Run::factory()->forRepository($repository)->create([
        'metrics' => null,
        'created_at' => now(),
    ]);

    $action = new GetTokenUsage;
    $result = $action->handle($workspace, 30);

    expect($result->first()['total_tokens'])->toBe(150);
});

it('respects days parameter', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $recentRun = Run::factory()->forRepository($repository)->create([
        'metrics' => ['input_tokens' => 100, 'output_tokens' => 50],
    ]);

    $oldRun = Run::factory()->forRepository($repository)->create([
        'metrics' => ['input_tokens' => 500, 'output_tokens' => 200],
    ]);

    // Update created_at via DB to avoid factory overrides
    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $recentRun->id)
        ->update(['created_at' => now()->subDays(5)]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $oldRun->id)
        ->update(['created_at' => now()->subDays(40)]);

    $action = new GetTokenUsage;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
    expect($result->first()['total_tokens'])->toBe(150);
});

it('handles missing token fields in metrics', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->forRepository($repository)->create([
        'metrics' => ['other_field' => 'value'],
        'created_at' => now(),
    ]);

    $action = new GetTokenUsage;
    $result = $action->handle($workspace, 30);

    expect($result->first()['total_tokens'])->toBe(0);
});

it('sorts results by date', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $run1 = Run::factory()->forRepository($repository)->create([
        'metrics' => ['input_tokens' => 100, 'output_tokens' => 50],
    ]);
    $run2 = Run::factory()->forRepository($repository)->create([
        'metrics' => ['input_tokens' => 200, 'output_tokens' => 100],
    ]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $run1->id)
        ->update(['created_at' => now()->subDays(2)]);

    Illuminate\Support\Facades\DB::table('runs')
        ->where('id', $run2->id)
        ->update(['created_at' => now()->subDays(5)]);

    $action = new GetTokenUsage;
    $result = $action->handle($workspace, 30);

    $dates = $result->pluck('date')->toArray();

    expect($dates[0])->toBeLessThan($dates[1]);
});
