<?php

declare(strict_types=1);

use App\Actions\Analytics\GetResolutionRate;
use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns resolution rate by date', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->forRun($run)->create();

    $action = new GetResolutionRate;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
    expect($result->first())->toHaveKeys([
        'date',
        'total_findings',
        'annotated_findings',
        'annotation_rate',
        'avg_time_to_annotation_seconds',
    ]);
});

it('calculates annotation rate correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $annotatedFinding = Finding::factory()->forRun($run)->create();
    Annotation::factory()->forFinding($annotatedFinding)->create();

    Finding::factory()->forRun($run)->create();

    $action = new GetResolutionRate;
    $result = $action->handle($workspace, 30);

    expect($result->first()['total_findings'])->toBe(2);
    expect($result->first()['annotated_findings'])->toBe(1);
    expect($result->first()['annotation_rate'])->toBe(50.0);
});

it('returns empty collection when no findings', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetResolutionRate;
    $result = $action->handle($workspace, 30);

    expect($result)->toBeEmpty();
});

it('calculates avg time to annotation', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $finding = Finding::factory()->forRun($run)->create();
    Annotation::factory()->forFinding($finding)->create();

    $action = new GetResolutionRate;
    $result = $action->handle($workspace, 30);

    expect($result->first()['avg_time_to_annotation_seconds'])->not->toBeNull();
});

it('respects days parameter', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $recentFinding = Finding::factory()->forRun($run)->create();

    $oldFinding = Finding::factory()->forRun($run)->create();

    Illuminate\Support\Facades\DB::table('findings')
        ->where('id', $recentFinding->id)
        ->update(['created_at' => now()->subDays(5)]);

    Illuminate\Support\Facades\DB::table('findings')
        ->where('id', $oldFinding->id)
        ->update(['created_at' => now()->subDays(40)]);

    $action = new GetResolutionRate;
    $result = $action->handle($workspace, 30);

    expect($result)->toHaveCount(1);
});
