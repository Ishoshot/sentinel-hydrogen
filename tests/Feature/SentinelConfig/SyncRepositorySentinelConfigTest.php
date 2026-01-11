<?php

declare(strict_types=1);

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Actions\SentinelConfig\SyncRepositorySentinelConfig;
use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Enums\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;

beforeEach(function (): void {
    Provider::firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

it('syncs valid config successfully', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    RepositorySettings::factory()->forRepository($repository)->create();

    $yamlContent = <<<'YAML'
version: 1
triggers:
  target_branches:
    - main
    - develop
review:
  min_severity: medium
YAML;

    $this->mock(FetchesSentinelConfig::class)
        ->shouldReceive('handle')
        ->once()
        ->andReturn([
            'found' => true,
            'content' => $yamlContent,
            'sha' => 'abc123',
            'error' => null,
        ]);

    $action = app(SyncRepositorySentinelConfig::class);
    $result = $action->handle($repository);

    expect($result['synced'])->toBeTrue();
    expect($result['config'])->toBeInstanceOf(SentinelConfig::class);
    expect($result['error'])->toBeNull();

    $repository->refresh();
    $settings = $repository->settings;

    expect($settings->sentinel_config)->not->toBeNull();
    expect($settings->sentinel_config['version'])->toBe(1);
    expect($settings->config_synced_at)->not->toBeNull();
    expect($settings->config_error)->toBeNull();
});

it('clears config when file does not exist', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    RepositorySettings::factory()->forRepository($repository)->create([
        'sentinel_config' => ['version' => 1],
        'config_synced_at' => now()->subDay(),
    ]);

    $this->mock(FetchesSentinelConfig::class)
        ->shouldReceive('handle')
        ->once()
        ->andReturn([
            'found' => false,
            'content' => null,
            'sha' => null,
            'error' => null,
        ]);

    $action = app(SyncRepositorySentinelConfig::class);
    $result = $action->handle($repository);

    expect($result['synced'])->toBeTrue();
    expect($result['config'])->toBeNull();
    expect($result['error'])->toBeNull();

    $repository->refresh();
    $settings = $repository->settings;

    expect($settings->sentinel_config)->toBeNull();
    expect($settings->config_synced_at)->not->toBeNull();
    expect($settings->config_error)->toBeNull();
});

it('stores error when config is invalid', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    RepositorySettings::factory()->forRepository($repository)->create([
        'sentinel_config' => ['version' => 1],
    ]);

    $invalidYaml = <<<'YAML'
version: 999
review:
  min_severity: invalid_value
YAML;

    $this->mock(FetchesSentinelConfig::class)
        ->shouldReceive('handle')
        ->once()
        ->andReturn([
            'found' => true,
            'content' => $invalidYaml,
            'sha' => 'abc123',
            'error' => null,
        ]);

    $action = app(SyncRepositorySentinelConfig::class);
    $result = $action->handle($repository);

    expect($result['synced'])->toBeFalse();
    expect($result['config'])->toBeNull();
    expect($result['error'])->not->toBeNull();

    $repository->refresh();
    $settings = $repository->settings;

    // Old config should be preserved
    expect($settings->sentinel_config)->toBe(['version' => 1]);
    expect($settings->config_error)->not->toBeNull();
});

it('stores error when fetch fails', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    RepositorySettings::factory()->forRepository($repository)->create();

    $this->mock(FetchesSentinelConfig::class)
        ->shouldReceive('handle')
        ->once()
        ->andReturn([
            'found' => false,
            'content' => null,
            'sha' => null,
            'error' => 'GitHub API error: Rate limit exceeded',
        ]);

    $action = app(SyncRepositorySentinelConfig::class);
    $result = $action->handle($repository);

    expect($result['synced'])->toBeFalse();
    expect($result['error'])->toContain('Rate limit');

    $repository->refresh();
    expect($repository->settings->config_error)->toContain('Rate limit');
});

it('fails gracefully when repository has no settings', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    // Ensure no settings exist
    RepositorySettings::where('repository_id', $repository->id)->delete();

    $action = app(SyncRepositorySentinelConfig::class);
    $result = $action->handle($repository);

    expect($result['synced'])->toBeFalse();
    expect($result['error'])->toBe('Repository has no settings');
});

it('stores complete config with all sections', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    RepositorySettings::factory()->forRepository($repository)->create();

    $yamlContent = <<<'YAML'
version: 1
triggers:
  target_branches:
    - main
  skip_labels:
    - skip-review
paths:
  ignore:
    - "*.lock"
review:
  min_severity: low
  max_findings: 50
  tone: educational
guidelines:
  - path: docs/STANDARDS.md
    description: Team standards
annotations:
  style: review
  grouped: true
YAML;

    $this->mock(FetchesSentinelConfig::class)
        ->shouldReceive('handle')
        ->once()
        ->andReturn([
            'found' => true,
            'content' => $yamlContent,
            'sha' => 'abc123',
            'error' => null,
        ]);

    $action = app(SyncRepositorySentinelConfig::class);
    $result = $action->handle($repository);

    expect($result['synced'])->toBeTrue();

    $config = $result['config'];
    expect($config->triggers->targetBranches)->toBe(['main']);
    expect($config->triggers->skipLabels)->toBe(['skip-review']);
    expect($config->paths->ignore)->toBe(['*.lock']);
    expect($config->review->maxFindings)->toBe(50);
    expect($config->guidelines)->toHaveCount(1);
    expect($config->annotations->grouped)->toBeTrue();
});

describe('RepositorySettings DTO accessors', function (): void {
    it('returns null when no config is set', function (): void {
        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create();
        $repository = Repository::factory()->forInstallation($installation)->create();
        $settings = RepositorySettings::factory()->forRepository($repository)->create([
            'sentinel_config' => null,
        ]);

        expect($settings->getSentinelConfigDto())->toBeNull();
    });

    it('returns DTO when config is set', function (): void {
        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create();
        $repository = Repository::factory()->forInstallation($installation)->create();
        $settings = RepositorySettings::factory()->forRepository($repository)->create([
            'sentinel_config' => [
                'version' => 1,
                'review' => [
                    'min_severity' => 'high',
                ],
            ],
        ]);

        $dto = $settings->getSentinelConfigDto();

        expect($dto)->toBeInstanceOf(SentinelConfig::class);
        expect($dto->version)->toBe(1);
    });

    it('returns default config when none is set', function (): void {
        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create();
        $repository = Repository::factory()->forInstallation($installation)->create();
        $settings = RepositorySettings::factory()->forRepository($repository)->create([
            'sentinel_config' => null,
        ]);

        $dto = $settings->getConfigOrDefault();

        expect($dto)->toBeInstanceOf(SentinelConfig::class);
        expect($dto->version)->toBe(SentinelConfig::CURRENT_VERSION);
    });

    it('detects config errors', function (): void {
        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create();
        $repository = Repository::factory()->forInstallation($installation)->create();
        $settings = RepositorySettings::factory()->forRepository($repository)->create([
            'config_error' => null,
        ]);
        expect($settings->hasConfigError())->toBeFalse();

        $settings->config_error = 'Some error';
        expect($settings->hasConfigError())->toBeTrue();
    });
});
