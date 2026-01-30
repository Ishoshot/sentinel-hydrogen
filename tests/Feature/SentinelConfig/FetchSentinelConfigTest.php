<?php

declare(strict_types=1);

use App\Actions\SentinelConfig\FetchSentinelConfig;
use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Github\Exception\RuntimeException;

beforeEach(function (): void {
    Provider::firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

it('returns found true with content when config file exists', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'owner/repo',
        'default_branch' => 'main',
    ]);

    $configContent = "version: 1\ntriggers:\n  target_branches:\n    - main";
    $encodedContent = base64_encode($configContent);

    $mockGitHub = $this->mock(GitHubApiServiceContract::class);
    $mockGitHub->shouldReceive('getFileContents')
        ->with($installation->installation_id, 'owner', 'repo', '.sentinel/config.yaml', 'main')
        ->once()
        ->andReturn([
            'content' => $encodedContent,
            'encoding' => 'base64',
            'sha' => 'abc123',
        ]);

    $action = app(FetchSentinelConfig::class);
    $result = $action->handle($repository);

    expect($result['found'])->toBeTrue();
    expect($result['content'])->toBe($configContent);
    expect($result['sha'])->toBe('abc123');
    expect($result['error'])->toBeNull();
});

it('returns found false when config file does not exist', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'owner/repo',
        'default_branch' => 'main',
    ]);

    $mockGitHub = $this->mock(GitHubApiServiceContract::class);
    $mockGitHub->shouldReceive('getFileContents')
        ->once()
        ->andThrow(new RuntimeException('Not Found', 404));

    $action = app(FetchSentinelConfig::class);
    $result = $action->handle($repository);

    expect($result['found'])->toBeFalse();
    expect($result['content'])->toBeNull();
    expect($result['error'])->toBeNull();
});

it('returns error when GitHub API fails', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'owner/repo',
        'default_branch' => 'main',
    ]);

    $mockGitHub = $this->mock(GitHubApiServiceContract::class);
    $mockGitHub->shouldReceive('getFileContents')
        ->once()
        ->andThrow(new RuntimeException('Rate limit exceeded', 403));

    $action = app(FetchSentinelConfig::class);
    $result = $action->handle($repository);

    expect($result['found'])->toBeFalse();
    expect($result['content'])->toBeNull();
    expect($result['error'])->toContain('Rate limit exceeded');
});

// Note: Test for "repository has no installation" is omitted because the database
// schema enforces that all repositories must have an installation_id (foreign key constraint).
// The defensive check in FetchSentinelConfig handles edge cases that can't occur in practice.

it('handles base64 decoding correctly', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'owner/repo',
        'default_branch' => 'develop',
    ]);

    $yamlContent = <<<'YAML'
version: 1
review:
  min_severity: high
  max_findings: 10
YAML;

    $mockGitHub = $this->mock(GitHubApiServiceContract::class);
    $mockGitHub->shouldReceive('getFileContents')
        ->with($installation->installation_id, 'owner', 'repo', '.sentinel/config.yaml', 'develop')
        ->once()
        ->andReturn([
            'content' => base64_encode($yamlContent),
            'encoding' => 'base64',
            'sha' => 'def456',
        ]);

    $action = app(FetchSentinelConfig::class);
    $result = $action->handle($repository);

    expect($result['found'])->toBeTrue();
    expect($result['content'])->toBe($yamlContent);
});
