<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\FileContextCollector;
use App\Services\Context\ContextBag;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

it('has correct name', function (): void {
    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    expect($collector->name())->toBe('file_context');
});

it('has correct priority', function (): void {
    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    expect($collector->priority())->toBe(85);
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

    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    expect($collector->shouldCollect([
        'repository' => $repository,
        'run' => $run,
    ]))->toBeTrue();
});

it('should not collect when repository is missing', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    expect($collector->shouldCollect(['run' => $run]))->toBeFalse();
});

it('should not collect when run is missing', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    expect($collector->shouldCollect(['repository' => $repository]))->toBeFalse();
});

it('does nothing when coordinates cannot be resolved', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => '', // empty full_name prevents coordinate resolution
    ]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['head_sha' => 'abc123'],
    ]);

    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHub->shouldNotReceive('getFileContents');

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    $bag = new ContextBag(files: [
        ['filename' => 'src/App.php', 'status' => 'modified', 'additions' => 5, 'deletions' => 2, 'changes' => 7, 'patch' => '+code'],
    ]);

    $collector->collect($bag, ['repository' => $repository, 'run' => $run]);

    expect($bag->fileContents)->toBeEmpty();
});

it('does nothing when head SHA is missing', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [], // no head_sha
    ]);

    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHub->shouldNotReceive('getFileContents');

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    $bag = new ContextBag(files: [
        ['filename' => 'src/App.php', 'status' => 'modified', 'additions' => 5, 'deletions' => 2, 'changes' => 7, 'patch' => '+code'],
    ]);

    $collector->collect($bag, ['repository' => $repository, 'run' => $run]);

    expect($bag->fileContents)->toBeEmpty();
});

it('skips when no suitable files after selection', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['head_sha' => 'abc123'],
    ]);

    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHub->shouldNotReceive('getFileContents');

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    // All files removed — FileSelectionPolicy filters them out
    $bag = new ContextBag(files: [
        ['filename' => 'src/App.php', 'status' => 'removed', 'additions' => 0, 'deletions' => 10, 'changes' => 10, 'patch' => '-code'],
    ]);

    $collector->collect($bag, ['repository' => $repository, 'run' => $run]);

    expect($bag->fileContents)->toBeEmpty();
});

it('fetches file contents successfully', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['head_sha' => 'abc123'],
    ]);

    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHub->shouldReceive('getFileContents')
        ->with(12345, 'test-owner', 'test-repo', 'src/App.php', 'abc123')
        ->once()
        ->andReturn('<?php class App {}');
    $mockGitHub->shouldReceive('getFileContents')
        ->with(12345, 'test-owner', 'test-repo', 'src/Helper.php', 'abc123')
        ->once()
        ->andReturn('<?php function helper() {}');

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    $bag = new ContextBag(files: [
        ['filename' => 'src/App.php', 'status' => 'modified', 'additions' => 5, 'deletions' => 2, 'changes' => 7, 'patch' => '+code'],
        ['filename' => 'src/Helper.php', 'status' => 'added', 'additions' => 3, 'deletions' => 0, 'changes' => 3, 'patch' => '+code'],
    ]);

    $collector->collect($bag, ['repository' => $repository, 'run' => $run]);

    expect($bag->fileContents)
        ->toHaveCount(2)
        ->toHaveKey('src/App.php')
        ->toHaveKey('src/Helper.php')
        ->and($bag->fileContents['src/App.php'])->toBe('<?php class App {}')
        ->and($bag->fileContents['src/Helper.php'])->toBe('<?php function helper() {}');
});

it('handles fetch failures gracefully', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['head_sha' => 'abc123'],
    ]);

    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHub->shouldReceive('getFileContents')
        ->with(12345, 'test-owner', 'test-repo', 'src/App.php', 'abc123')
        ->once()
        ->andThrow(new RuntimeException('API error'));
    $mockGitHub->shouldReceive('getFileContents')
        ->with(12345, 'test-owner', 'test-repo', 'src/Helper.php', 'abc123')
        ->once()
        ->andReturn('<?php function helper() {}');

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    $bag = new ContextBag(files: [
        ['filename' => 'src/App.php', 'status' => 'modified', 'additions' => 5, 'deletions' => 2, 'changes' => 7, 'patch' => '+code'],
        ['filename' => 'src/Helper.php', 'status' => 'added', 'additions' => 3, 'deletions' => 0, 'changes' => 3, 'patch' => '+code'],
    ]);

    $collector->collect($bag, ['repository' => $repository, 'run' => $run]);

    expect($bag->fileContents)
        ->toHaveCount(1)
        ->toHaveKey('src/Helper.php')
        ->not->toHaveKey('src/App.php');
});

it('skips oversized files', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['head_sha' => 'abc123'],
    ]);

    // Return a string larger than MAX_FILE_SIZE (50000)
    $mockGitHub = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHub->shouldReceive('getFileContents')
        ->with(12345, 'test-owner', 'test-repo', 'src/Large.php', 'abc123')
        ->once()
        ->andReturn(str_repeat('x', 60000));

    /** @var GitHubApiServiceContract $mockGitHub */
    $collector = new FileContextCollector($mockGitHub);

    $bag = new ContextBag(files: [
        ['filename' => 'src/Large.php', 'status' => 'modified', 'additions' => 100, 'deletions' => 50, 'changes' => 150, 'patch' => '+code'],
    ]);

    $collector->collect($bag, ['repository' => $repository, 'run' => $run]);

    expect($bag->fileContents)->toBeEmpty();
});
