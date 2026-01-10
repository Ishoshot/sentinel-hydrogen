<?php

declare(strict_types=1);

use App\Actions\Reviews\CreatePullRequestRun;
use App\Enums\ProviderType;
use App\Enums\RunStatus;
use App\Jobs\GitHub\ProcessPullRequestWebhook;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\Run;
use App\Services\GitHub\GitHubWebhookService;

it('creates a run for pull request webhook when auto review is enabled', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345678,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'github_id' => 987654,
        'full_name' => 'org/repo',
        'name' => 'repo',
    ]);
    RepositorySettings::factory()->forRepository($repository)->autoReviewEnabled()->create();

    $payload = [
        'action' => 'opened',
        'installation' => ['id' => 12345678],
        'repository' => [
            'id' => 987654,
            'full_name' => 'org/repo',
        ],
        'pull_request' => [
            'number' => 42,
            'title' => 'Test PR',
            'body' => 'Test body',
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(app(GitHubWebhookService::class), app(CreatePullRequestRun::class));

    $run = Run::query()->first();

    expect($run)->not->toBeNull()
        ->and($run?->status)->toBe(RunStatus::Queued)
        ->and($run?->external_reference)->toBe('github:pull_request:42:abc123');
});

it('skips run creation when auto review is disabled', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345678,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'github_id' => 987654,
        'full_name' => 'org/repo',
        'name' => 'repo',
    ]);
    RepositorySettings::factory()->forRepository($repository)->autoReviewDisabled()->create();

    $payload = [
        'action' => 'opened',
        'installation' => ['id' => 12345678],
        'repository' => [
            'id' => 987654,
            'full_name' => 'org/repo',
        ],
        'pull_request' => [
            'number' => 42,
            'title' => 'Test PR',
            'body' => 'Test body',
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(app(GitHubWebhookService::class), app(CreatePullRequestRun::class));

    expect(Run::query()->count())->toBe(0);
});
