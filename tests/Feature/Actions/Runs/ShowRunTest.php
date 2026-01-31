<?php

declare(strict_types=1);

use App\Actions\Runs\ShowRun;
use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns the run', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $action = new ShowRun;
    $result = $action->handle($run);

    expect($result)->toBeInstanceOf(Run::class);
    expect($result->id)->toBe($run->id);
});

it('loads repository relationship', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $action = new ShowRun;
    $result = $action->handle($run);

    expect($result->relationLoaded('repository'))->toBeTrue();
    expect($result->repository->id)->toBe($repository->id);
});

it('loads findings relationship', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->count(3)->forRun($run)->create();

    $action = new ShowRun;
    $result = $action->handle($run);

    expect($result->relationLoaded('findings'))->toBeTrue();
    expect($result->findings)->toHaveCount(3);
});

it('loads findings annotations', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();
    $finding = Finding::factory()->forRun($run)->create();

    Annotation::factory()->count(2)->forFinding($finding)->create();

    $action = new ShowRun;
    $result = $action->handle($run);

    expect($result->findings->first()->relationLoaded('annotations'))->toBeTrue();
    expect($result->findings->first()->annotations)->toHaveCount(2);
});

it('handles run with no findings', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $action = new ShowRun;
    $result = $action->handle($run);

    expect($result->findings)->toBeEmpty();
});
