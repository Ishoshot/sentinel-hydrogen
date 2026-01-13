<?php

declare(strict_types=1);

use App\Enums\InstallationStatus;
use App\Enums\ProviderType;
use App\Jobs\GitHub\ProcessInstallationRepositoriesWebhook;
use App\Jobs\GitHub\ProcessInstallationWebhook;
use App\Jobs\GitHub\ProcessPullRequestWebhook;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use Illuminate\Support\Facades\Queue;
use Mockery;

beforeEach(function (): void {
    config(['github.webhook_secret' => 'test-secret']);
    Provider::firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

function createWebhookSignature(string $payload, string $secret = 'test-secret'): string
{
    return 'sha256='.hash_hmac('sha256', $payload, $secret);
}

it('verifies webhook signature', function (): void {
    Queue::fake();

    $payload = json_encode([
        'action' => 'created',
        'installation' => [
            'id' => 12345678,
            'account' => [
                'type' => 'User',
                'login' => 'testuser',
                'avatar_url' => 'https://example.com/avatar.jpg',
            ],
            'permissions' => ['contents' => 'read'],
            'events' => ['push'],
        ],
    ]);

    $response = $this->postJson(
        route('webhooks.github'),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => createWebhookSignature($payload),
            'X-GitHub-Event' => 'installation',
            'X-GitHub-Delivery' => 'test-delivery-id',
            'Content-Type' => 'application/json',
        ]
    );

    $response->assertOk();
    Queue::assertPushed(ProcessInstallationWebhook::class);
});

it('rejects invalid webhook signature', function (): void {
    $payload = json_encode(['action' => 'created']);

    $response = $this->postJson(
        route('webhooks.github'),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => 'sha256=invalid-signature',
            'X-GitHub-Event' => 'installation',
            'X-GitHub-Delivery' => 'test-delivery-id',
            'Content-Type' => 'application/json',
        ]
    );

    $response->assertUnauthorized()
        ->assertJsonPath('error', 'Invalid signature');
});

it('dispatches installation webhook job', function (): void {
    Queue::fake();

    $payload = json_encode([
        'action' => 'created',
        'installation' => [
            'id' => 12345678,
            'account' => [
                'type' => 'Organization',
                'login' => 'test-org',
                'avatar_url' => null,
            ],
            'permissions' => [],
            'events' => [],
        ],
    ]);

    $response = $this->postJson(
        route('webhooks.github'),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => createWebhookSignature($payload),
            'X-GitHub-Event' => 'installation',
            'X-GitHub-Delivery' => 'test-delivery-id',
            'Content-Type' => 'application/json',
        ]
    );

    $response->assertOk();
    Queue::assertPushed(ProcessInstallationWebhook::class, function ($job) {
        return $job->payload['action'] === 'created'
            && $job->payload['installation']['id'] === 12345678;
    });
});

it('dispatches installation repositories webhook job', function (): void {
    Queue::fake();

    $payload = json_encode([
        'action' => 'added',
        'installation' => ['id' => 12345678],
        'repositories_added' => [
            ['id' => 1, 'name' => 'repo1', 'full_name' => 'org/repo1', 'private' => false],
        ],
        'repositories_removed' => [],
    ]);

    $response = $this->postJson(
        route('webhooks.github'),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => createWebhookSignature($payload),
            'X-GitHub-Event' => 'installation_repositories',
            'X-GitHub-Delivery' => 'test-delivery-id',
            'Content-Type' => 'application/json',
        ]
    );

    $response->assertOk();
    Queue::assertPushed(ProcessInstallationRepositoriesWebhook::class);
});

it('dispatches pull request webhook job', function (): void {
    Queue::fake();

    $payload = json_encode([
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
    ]);

    $response = $this->postJson(
        route('webhooks.github'),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => createWebhookSignature($payload),
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => 'test-delivery-id',
            'Content-Type' => 'application/json',
        ]
    );

    $response->assertOk();
    Queue::assertPushed(ProcessPullRequestWebhook::class);
});

it('ignores unsupported webhook events', function (): void {
    Queue::fake();

    $payload = json_encode(['action' => 'created']);

    $response = $this->postJson(
        route('webhooks.github'),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => createWebhookSignature($payload),
            'X-GitHub-Event' => 'unsupported_event',
            'X-GitHub-Delivery' => 'test-delivery-id',
            'Content-Type' => 'application/json',
        ]
    );

    $response->assertOk()
        ->assertJsonPath('message', 'Event ignored');

    Queue::assertNothingPushed();
});

it('processes installation deleted webhook', function (): void {
    Queue::fake();

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345678,
    ]);

    $payload = json_encode([
        'action' => 'deleted',
        'installation' => [
            'id' => 12345678,
            'account' => [
                'type' => 'User',
                'login' => 'testuser',
                'avatar_url' => null,
            ],
            'permissions' => [],
            'events' => [],
        ],
    ]);

    $response = $this->postJson(
        route('webhooks.github'),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => createWebhookSignature($payload),
            'X-GitHub-Event' => 'installation',
            'X-GitHub-Delivery' => 'test-delivery-id',
            'Content-Type' => 'application/json',
        ]
    );

    $response->assertOk();
    Queue::assertPushed(ProcessInstallationWebhook::class);

    // Process the job manually with mocked service
    $appServiceMock = Mockery::mock(GitHubAppServiceContract::class);
    $appServiceMock->shouldReceive('clearInstallationToken')->once()->andReturnNull();

    $job = new ProcessInstallationWebhook(json_decode($payload, true));
    $job->handle(
        app(App\Services\GitHub\GitHubWebhookService::class),
        $appServiceMock
    );

    $installation->refresh();
    expect($installation->status)->toBe(InstallationStatus::Uninstalled);
});

it('processes installation suspended webhook', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->active()->create([
        'installation_id' => 12345678,
    ]);

    $payload = json_encode([
        'action' => 'suspend',
        'installation' => [
            'id' => 12345678,
            'account' => [
                'type' => 'User',
                'login' => 'testuser',
                'avatar_url' => null,
            ],
            'permissions' => [],
            'events' => [],
        ],
    ]);

    // Mock the contract since GitHubAppService is final
    $appServiceMock = Mockery::mock(GitHubAppServiceContract::class);

    $job = new ProcessInstallationWebhook(json_decode($payload, true));
    $job->handle(
        app(App\Services\GitHub\GitHubWebhookService::class),
        $appServiceMock
    );

    $installation->refresh();
    expect($installation->status)->toBe(InstallationStatus::Suspended);
    expect($installation->suspended_at)->not->toBeNull();
});

it('processes repositories added webhook', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345678,
    ]);

    $payload = json_encode([
        'action' => 'added',
        'installation' => ['id' => 12345678],
        'repositories_added' => [
            ['id' => 111, 'name' => 'new-repo', 'full_name' => 'org/new-repo', 'private' => false],
        ],
        'repositories_removed' => [],
    ]);

    $job = new ProcessInstallationRepositoriesWebhook(json_decode($payload, true));
    $job->handle(
        app(App\Services\GitHub\GitHubWebhookService::class),
        app(App\Actions\GitHub\SyncInstallationRepositories::class)
    );

    expect(Repository::where('github_id', 111)->exists())->toBeTrue();
});

it('processes repositories removed webhook', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345678,
    ]);
    Repository::factory()->forInstallation($installation)->create([
        'github_id' => 222,
        'name' => 'old-repo',
        'full_name' => 'org/old-repo',
    ]);

    $payload = json_encode([
        'action' => 'removed',
        'installation' => ['id' => 12345678],
        'repositories_added' => [],
        'repositories_removed' => [
            ['id' => 222, 'name' => 'old-repo', 'full_name' => 'org/old-repo'],
        ],
    ]);

    $job = new ProcessInstallationRepositoriesWebhook(json_decode($payload, true));
    $job->handle(
        app(App\Services\GitHub\GitHubWebhookService::class),
        app(App\Actions\GitHub\SyncInstallationRepositories::class)
    );

    expect(Repository::where('github_id', 222)->exists())->toBeFalse();
});
