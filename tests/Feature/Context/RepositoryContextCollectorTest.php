<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\RepositoryContextCollector;

it('has correct priority', function (): void {
    $collector = app(RepositoryContextCollector::class);

    expect($collector->priority())->toBe(50);
});

it('has correct name', function (): void {
    $collector = app(RepositoryContextCollector::class);

    expect($collector->name())->toBe('repository_context');
});

it('should collect when repository and run are provided', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();

    $collector = app(RepositoryContextCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($shouldCollect)->toBeTrue();
});

it('should not collect when repository is missing', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->make();

    $collector = app(RepositoryContextCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'run' => $run,
    ]);

    expect($shouldCollect)->toBeFalse();
});

it('should not collect when run is missing', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->make();

    $collector = app(RepositoryContextCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $repository,
    ]);

    expect($shouldCollect)->toBeFalse();
});
