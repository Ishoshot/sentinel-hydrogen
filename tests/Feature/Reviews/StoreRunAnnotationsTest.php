<?php

declare(strict_types=1);

use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\Reviews\StoreRunAnnotations;

it('stores annotations for findings', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();
    $findings = Finding::factory()->count(3)->create([
        'run_id' => $run->id,
    ]);

    $service = new StoreRunAnnotations;
    $service->handle($run, $findings, ['id' => 'review_123']);

    $this->assertDatabaseCount('annotations', 3);
});

it('handles review response without id', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();
    $findings = Finding::factory()->count(1)->create([
        'run_id' => $run->id,
    ]);

    $service = new StoreRunAnnotations;
    $service->handle($run, $findings, []);

    $this->assertDatabaseCount('annotations', 1);
});
