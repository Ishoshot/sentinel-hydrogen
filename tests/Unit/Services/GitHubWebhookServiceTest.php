<?php

declare(strict_types=1);

use App\Enums\GitHub\GitHubWebhookEvent;
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

    expect($result->action)->toBe('created');
    expect($result->installationId)->toBe(12345678);
    expect($result->accountType)->toBe('Organization');
    expect($result->accountLogin)->toBe('test-org');
    expect($result->accountAvatarUrl)->toBe('https://example.com/avatar.png');
    expect($result->permissions)->toBe(['contents' => 'read']);
    expect($result->events)->toBe(['push', 'pull_request']);
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

    expect($result->action)->toBe('added');
    expect($result->installationId)->toBe(12345678);
    expect($result->repositoriesAdded)->toHaveCount(2);
    expect($result->repositoriesRemoved)->toBeEmpty();
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
            'draft' => false,
            'user' => [
                'login' => 'pr-author',
                'avatar_url' => 'https://example.com/author-avatar.png',
            ],
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature-branch', 'sha' => 'abc123def'],
            'assignees' => [
                ['login' => 'assignee1', 'avatar_url' => 'https://example.com/a1.png'],
            ],
            'requested_reviewers' => [
                ['login' => 'reviewer1', 'avatar_url' => 'https://example.com/r1.png'],
                ['login' => 'reviewer2', 'avatar_url' => 'https://example.com/r2.png'],
            ],
            'labels' => [
                ['name' => 'enhancement', 'color' => '84b6eb'],
                ['name' => 'needs-review', 'color' => 'fbca04'],
            ],
        ],
        'sender' => ['login' => 'contributor'],
    ];

    $result = $service->parsePullRequestPayload($payload);

    expect($result)->toBeInstanceOf(App\Services\GitHub\ValueObjects\PullRequestWebhookPayload::class);
    expect($result->action)->toBe('opened');
    expect($result->installationId)->toBe(12345678);
    expect($result->repositoryId)->toBe(987654);
    expect($result->repositoryFullName)->toBe('org/repo');
    expect($result->pullRequestNumber)->toBe(42);
    expect($result->pullRequestTitle)->toBe('Add new feature');
    expect($result->pullRequestBody)->toBe('This PR adds a new feature');
    expect($result->baseBranch)->toBe('main');
    expect($result->headBranch)->toBe('feature-branch');
    expect($result->headSha)->toBe('abc123def');
    expect($result->senderLogin)->toBe('contributor');
    expect($result->isDraft)->toBeFalse();
    expect($result->author)->toBeInstanceOf(App\Services\Reviews\ValueObjects\GitHubUser::class);
    expect($result->author->login)->toBe('pr-author');
    expect($result->author->avatarUrl)->toBe('https://example.com/author-avatar.png');
    expect($result->assignees)->toHaveCount(1);
    expect($result->assignees[0]->login)->toBe('assignee1');
    expect($result->reviewers)->toHaveCount(2);
    expect($result->reviewers[0]->login)->toBe('reviewer1');
    expect($result->labels)->toHaveCount(2);
    expect($result->labels[0]->name)->toBe('enhancement');
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

it('determines if action should sync metadata', function (): void {
    $service = new GitHubWebhookService;

    // Actions that should sync metadata
    expect($service->shouldSyncMetadata('labeled'))->toBeTrue();
    expect($service->shouldSyncMetadata('unlabeled'))->toBeTrue();
    expect($service->shouldSyncMetadata('assigned'))->toBeTrue();
    expect($service->shouldSyncMetadata('unassigned'))->toBeTrue();
    expect($service->shouldSyncMetadata('review_requested'))->toBeTrue();
    expect($service->shouldSyncMetadata('review_request_removed'))->toBeTrue();
    expect($service->shouldSyncMetadata('converted_to_draft'))->toBeTrue();
    expect($service->shouldSyncMetadata('ready_for_review'))->toBeTrue();

    // Actions that should NOT sync metadata
    expect($service->shouldSyncMetadata('opened'))->toBeFalse();
    expect($service->shouldSyncMetadata('synchronize'))->toBeFalse();
    expect($service->shouldSyncMetadata('closed'))->toBeFalse();
    expect($service->shouldSyncMetadata('merged'))->toBeFalse();
});

it('determines if action should clean up pull request pre-index', function (): void {
    $service = new GitHubWebhookService;

    expect($service->shouldCleanupPreIndex('closed'))->toBeTrue();
    expect($service->shouldCleanupPreIndex('opened'))->toBeFalse();
    expect($service->shouldCleanupPreIndex('synchronize'))->toBeFalse();
});
