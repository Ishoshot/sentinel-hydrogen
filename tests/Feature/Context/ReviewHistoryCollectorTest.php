<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Enums\Reviews\RunStatus;
use App\Models\Connection;
use App\Models\Finding;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\ReviewHistoryCollector;
use App\Services\Context\ContextBag;

it('has correct priority', function (): void {
    $collector = app(ReviewHistoryCollector::class);

    expect($collector->priority())->toBe(60);
});

it('has correct name', function (): void {
    $collector = app(ReviewHistoryCollector::class);

    expect($collector->name())->toBe('review_history');
});

it('should collect when repository, run and PR number are provided', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_number' => 123,
        ],
    ]);

    $collector = app(ReviewHistoryCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($shouldCollect)->toBeTrue();
});

it('should not collect when PR number is missing', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [],
    ]);

    $collector = app(ReviewHistoryCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($shouldCollect)->toBeFalse();
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

    $collector = app(ReviewHistoryCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'run' => $run,
    ]);

    expect($shouldCollect)->toBeFalse();
});

it('collects previous completed reviews for same PR', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $prNumber = 456;

    // Create previous completed run with findings
    $previousRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'metadata' => [
            'pull_request_number' => $prNumber,
        ],
    ]);

    Finding::factory()->forRun($previousRun)->create([
        'severity' => 'high',
        'category' => 'security',
        'title' => 'SQL Injection vulnerability',
        'file_path' => 'app/Models/User.php',
    ]);

    // Create current run
    $currentRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'metadata' => [
            'pull_request_number' => $prNumber,
        ],
    ]);

    $bag = new ContextBag();
    $collector = app(ReviewHistoryCollector::class);

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $currentRun,
    ]);

    expect($bag->reviewHistory)->toHaveCount(1)
        ->and($bag->reviewHistory[0]['run_id'])->toBe($previousRun->id)
        ->and($bag->reviewHistory[0]['findings_count'])->toBe(1)
        ->and($bag->reviewHistory[0]['key_findings'])->toHaveCount(1)
        ->and($bag->reviewHistory[0]['key_findings'][0]['title'])->toBe('SQL Injection vulnerability');
});

it('does not include queued or failed runs in history', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $prNumber = 789;

    // Create queued run (should not be included)
    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'metadata' => [
            'pull_request_number' => $prNumber,
        ],
    ]);

    // Create failed run (should not be included)
    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Failed,
        'metadata' => [
            'pull_request_number' => $prNumber,
        ],
    ]);

    // Current run
    $currentRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::InProgress,
        'metadata' => [
            'pull_request_number' => $prNumber,
        ],
    ]);

    $bag = new ContextBag();
    $collector = app(ReviewHistoryCollector::class);

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $currentRun,
    ]);

    expect($bag->reviewHistory)->toBeEmpty();
});

it('does not include runs from different PRs', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    // Create completed run for different PR
    Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Completed,
        'metadata' => [
            'pull_request_number' => 111,
        ],
    ]);

    // Current run for different PR number
    $currentRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::InProgress,
        'metadata' => [
            'pull_request_number' => 222,
        ],
    ]);

    $bag = new ContextBag();
    $collector = app(ReviewHistoryCollector::class);

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $currentRun,
    ]);

    expect($bag->reviewHistory)->toBeEmpty();
});

it('limits to maximum 3 previous reviews', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $prNumber = 333;

    // Create 5 completed runs
    for ($i = 0; $i < 5; $i++) {
        Run::factory()->forRepository($repository)->create([
            'status' => RunStatus::Completed,
            'metadata' => [
                'pull_request_number' => $prNumber,
            ],
        ]);
    }

    // Current run
    $currentRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::InProgress,
        'metadata' => [
            'pull_request_number' => $prNumber,
        ],
    ]);

    $bag = new ContextBag();
    $collector = app(ReviewHistoryCollector::class);

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $currentRun,
    ]);

    expect($bag->reviewHistory)->toHaveCount(3);
});
