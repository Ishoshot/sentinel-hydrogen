<?php

declare(strict_types=1);

use App\Actions\Reviews\Resolvers\PullRequestWebhookRepositoryResolver;
use App\Models\Installation;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->resolver = new PullRequestWebhookRepositoryResolver();
});

it('returns null when installation is not found', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message): bool => $message === 'Installation not found for pull request webhook');

    $payload = [
        'installation_id' => 99999999,
        'repository_id' => 12345,
    ];

    $result = $this->resolver->resolve($payload, ['action' => 'opened']);

    expect($result)->toBeNull();
});

it('returns null when repository is not found for installation', function (): void {
    $installation = Installation::factory()->create([
        'installation_id' => 11111111,
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message): bool => $message === 'Repository not found for pull request webhook');

    $payload = [
        'installation_id' => 11111111,
        'repository_id' => 99999,
    ];

    $result = $this->resolver->resolve($payload, ['action' => 'opened']);

    expect($result)->toBeNull();
});

it('returns repository when both installation and repository exist', function (): void {
    $installation = Installation::factory()->create([
        'installation_id' => 22222222,
    ]);

    $repository = Repository::factory()->forInstallation($installation)->create([
        'github_id' => 55555,
        'full_name' => 'org/my-repo',
    ]);

    $payload = [
        'installation_id' => 22222222,
        'repository_id' => 55555,
    ];

    $result = $this->resolver->resolve($payload, ['action' => 'opened']);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($repository->id)
        ->and($result->full_name)->toBe('org/my-repo');
});

it('does not return repository from a different installation', function (): void {
    $installation1 = Installation::factory()->create([
        'installation_id' => 33333333,
    ]);

    $installation2 = Installation::factory()->create([
        'installation_id' => 44444444,
    ]);

    Repository::factory()->forInstallation($installation1)->create([
        'github_id' => 77777,
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message): bool => $message === 'Repository not found for pull request webhook');

    $payload = [
        'installation_id' => 44444444,
        'repository_id' => 77777,
    ];

    $result = $this->resolver->resolve($payload, ['action' => 'opened']);

    expect($result)->toBeNull();
});

it('passes log context when installation is not found', function (): void {
    $logContext = [
        'action' => 'opened',
        'pr_number' => 42,
    ];

    Log::shouldReceive('warning')
        ->once()
        ->with('Installation not found for pull request webhook', $logContext);

    $payload = [
        'installation_id' => 88888888,
        'repository_id' => 12345,
    ];

    $this->resolver->resolve($payload, $logContext);
});

it('merges github_repository_id into log context when repository is not found', function (): void {
    $installation = Installation::factory()->create([
        'installation_id' => 55555555,
    ]);

    $logContext = ['action' => 'synchronize'];

    Log::shouldReceive('warning')
        ->once()
        ->with('Repository not found for pull request webhook', array_merge($logContext, [
            'github_repository_id' => 66666,
        ]));

    $payload = [
        'installation_id' => 55555555,
        'repository_id' => 66666,
    ];

    $this->resolver->resolve($payload, $logContext);
});
