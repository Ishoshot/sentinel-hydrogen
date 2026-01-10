<?php

declare(strict_types=1);

use App\Enums\GitHubWebhookEvent;
use App\Services\GitHub\GitHubWebhookService;

beforeEach(function (): void {
    config(['github.webhook_secret' => 'test-webhook-secret']);
});

it('verifies valid webhook signature', function (): void {
    $service = new GitHubWebhookService;
    $payload = '{"test": "data"}';
    $signature = 'sha256='.hash_hmac('sha256', $payload, 'test-webhook-secret');

    expect($service->verifySignature($payload, $signature))->toBeTrue();
});

it('rejects invalid webhook signature', function (): void {
    $service = new GitHubWebhookService;
    $payload = '{"test": "data"}';

    expect($service->verifySignature($payload, 'sha256=invalid'))->toBeFalse();
});

it('rejects signature when secret is not configured', function (): void {
    config(['github.webhook_secret' => null]);
    $service = new GitHubWebhookService;
    $payload = '{"test": "data"}';

    expect($service->verifySignature($payload, 'sha256=anything'))->toBeFalse();
});

it('parses valid event types', function (): void {
    $service = new GitHubWebhookService;

    expect($service->parseEventType('installation'))->toBe(GitHubWebhookEvent::Installation);
    expect($service->parseEventType('installation_repositories'))->toBe(GitHubWebhookEvent::InstallationRepositories);
    expect($service->parseEventType('pull_request'))->toBe(GitHubWebhookEvent::PullRequest);
    expect($service->parseEventType('pull_request_review'))->toBe(GitHubWebhookEvent::PullRequestReview);
    expect($service->parseEventType('push'))->toBe(GitHubWebhookEvent::Push);
});

it('returns null for unknown event types', function (): void {
    $service = new GitHubWebhookService;

    expect($service->parseEventType('unknown_event'))->toBeNull();
    expect($service->parseEventType('star'))->toBeNull();
});

it('extracts installation id from payload', function (): void {
    $service = new GitHubWebhookService;

    $payload = ['installation' => ['id' => 12345678]];
    expect($service->extractInstallationId($payload))->toBe(12345678);

    $payload = ['other' => 'data'];
    expect($service->extractInstallationId($payload))->toBeNull();
});

it('extracts action from payload', function (): void {
    $service = new GitHubWebhookService;

    $payload = ['action' => 'created'];
    expect($service->extractAction($payload))->toBe('created');

    $payload = ['other' => 'data'];
    expect($service->extractAction($payload))->toBeNull();
});

it('parses installation payload', function (): void {
    $service = new GitHubWebhookService;

    $payload = [
        'action' => 'created',
        'installation' => [
            'id' => 12345678,
            'account' => [
                'type' => 'Organization',
                'login' => 'test-org',
                'avatar_url' => 'https://example.com/avatar.png',
            ],
            'permissions' => ['contents' => 'read'],
            'events' => ['push', 'pull_request'],
        ],
    ];

    $result = $service->parseInstallationPayload($payload);

    expect($result['action'])->toBe('created');
    expect($result['installation_id'])->toBe(12345678);
    expect($result['account_type'])->toBe('Organization');
    expect($result['account_login'])->toBe('test-org');
    expect($result['account_avatar_url'])->toBe('https://example.com/avatar.png');
    expect($result['permissions'])->toBe(['contents' => 'read']);
    expect($result['events'])->toBe(['push', 'pull_request']);
});

it('parses installation repositories payload', function (): void {
    $service = new GitHubWebhookService;

    $payload = [
        'action' => 'added',
        'installation' => ['id' => 12345678],
        'repositories_added' => [
            ['id' => 1, 'name' => 'repo1', 'full_name' => 'org/repo1', 'private' => false],
            ['id' => 2, 'name' => 'repo2', 'full_name' => 'org/repo2', 'private' => true],
        ],
        'repositories_removed' => [],
    ];

    $result = $service->parseInstallationRepositoriesPayload($payload);

    expect($result['action'])->toBe('added');
    expect($result['installation_id'])->toBe(12345678);
    expect($result['repositories_added'])->toHaveCount(2);
    expect($result['repositories_removed'])->toBeEmpty();
});

it('parses pull request payload', function (): void {
    $service = new GitHubWebhookService;

    $payload = [
        'action' => 'opened',
        'installation' => ['id' => 12345678],
        'repository' => [
            'id' => 987654,
            'full_name' => 'org/repo',
        ],
        'pull_request' => [
            'number' => 42,
            'title' => 'Add new feature',
            'body' => 'This PR adds a new feature',
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature-branch', 'sha' => 'abc123def'],
        ],
        'sender' => ['login' => 'contributor'],
    ];

    $result = $service->parsePullRequestPayload($payload);

    expect($result['action'])->toBe('opened');
    expect($result['installation_id'])->toBe(12345678);
    expect($result['repository_id'])->toBe(987654);
    expect($result['repository_full_name'])->toBe('org/repo');
    expect($result['pull_request_number'])->toBe(42);
    expect($result['pull_request_title'])->toBe('Add new feature');
    expect($result['pull_request_body'])->toBe('This PR adds a new feature');
    expect($result['base_branch'])->toBe('main');
    expect($result['head_branch'])->toBe('feature-branch');
    expect($result['head_sha'])->toBe('abc123def');
    expect($result['sender_login'])->toBe('contributor');
});

it('determines if action should trigger review', function (): void {
    $service = new GitHubWebhookService;

    expect($service->shouldTriggerReview('opened'))->toBeTrue();
    expect($service->shouldTriggerReview('synchronize'))->toBeTrue();
    expect($service->shouldTriggerReview('reopened'))->toBeTrue();

    expect($service->shouldTriggerReview('closed'))->toBeFalse();
    expect($service->shouldTriggerReview('labeled'))->toBeFalse();
    expect($service->shouldTriggerReview('assigned'))->toBeFalse();
});
