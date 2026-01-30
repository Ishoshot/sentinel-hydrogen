<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\Briefings\BriefingDataGuard;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('denies generation when runs are below the minimum threshold', function (): void {
    config([
        'briefings.data_guard' => [
            'enabled' => true,
            'min_runs' => 3,
            'min_active_days' => 1,
            'min_repositories' => 1,
        ],
    ]);

    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $today = now()->startOfDay();
    $yesterday = now()->subDay()->startOfDay();

    Run::factory()->forRepository($repository)->create(['created_at' => $yesterday]);
    Run::factory()->forRepository($repository)->create(['created_at' => $today]);

    $parameters = BriefingParameters::fromArray([
        'start_date' => $yesterday->toDateString(),
        'end_date' => $today->toDateString(),
        'repository_ids' => [$repository->id],
    ]);

    $result = app(BriefingDataGuard::class)->check($workspace, $parameters);

    expect($result->isDenied())->toBeTrue()
        ->and($result->reason)->toContain('at least 3 runs');
});

it('denies generation when activity days are below the minimum threshold', function (): void {
    config([
        'briefings.data_guard' => [
            'enabled' => true,
            'min_runs' => 1,
            'min_active_days' => 2,
            'min_repositories' => 1,
        ],
    ]);

    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $today = now()->startOfDay();

    Run::factory()->forRepository($repository)->create(['created_at' => $today]);
    Run::factory()->forRepository($repository)->create(['created_at' => $today]);

    $parameters = BriefingParameters::fromArray([
        'start_date' => $today->copy()->subDay()->toDateString(),
        'end_date' => $today->toDateString(),
        'repository_ids' => [$repository->id],
    ]);

    $result = app(BriefingDataGuard::class)->check($workspace, $parameters);

    expect($result->isDenied())->toBeTrue()
        ->and($result->reason)->toContain('at least 2 days');
});

it('allows generation when minimum data requirements are met', function (): void {
    config([
        'briefings.data_guard' => [
            'enabled' => true,
            'min_runs' => 2,
            'min_active_days' => 2,
            'min_repositories' => 2,
        ],
    ]);

    $workspace = Workspace::factory()->create();
    $repositoryOne = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $repositoryTwo = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $today = now()->startOfDay();
    $yesterday = now()->subDay()->startOfDay();

    Run::factory()->forRepository($repositoryOne)->create(['created_at' => $yesterday]);
    Run::factory()->forRepository($repositoryTwo)->create(['created_at' => $today]);

    $parameters = BriefingParameters::fromArray([
        'start_date' => $yesterday->toDateString(),
        'end_date' => $today->toDateString(),
        'repository_ids' => [$repositoryOne->id, $repositoryTwo->id],
    ]);

    $result = app(BriefingDataGuard::class)->check($workspace, $parameters);

    expect($result->isAllowed())->toBeTrue();
});
