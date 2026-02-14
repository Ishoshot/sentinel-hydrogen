<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Services\Reviews\ManualPullRequestPayloadFactory;

it('builds manual review payload from GitHub pull request data', function (): void {
    $repository = new Repository([
        'github_id' => 987654,
        'full_name' => 'acme/repo',
    ]);

    $payload = app(ManualPullRequestPayloadFactory::class)->make(
        repository: $repository,
        installationId: 123456,
        pullRequestData: [
            'number' => 42,
            'title' => 'Improve queue routing',
            'body' => 'Adds shared dispatch action',
            'draft' => false,
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature/review-dispatch', 'sha' => 'abc123'],
            'user' => ['login' => 'octocat', 'avatar_url' => 'https://example.com/avatar.png'],
            'assignees' => [
                ['login' => 'maintainer', 'avatar_url' => 'https://example.com/m.png'],
            ],
            'requested_reviewers' => [
                ['login' => 'reviewer', 'avatar_url' => null],
            ],
            'labels' => [
                ['name' => 'enhancement', 'color' => '00ff00'],
            ],
        ],
        senderLogin: 'sender-user',
    );

    expect($payload)->toMatchArray([
        'action' => 'manual_trigger',
        'installation_id' => 123456,
        'repository_id' => 987654,
        'repository_full_name' => 'acme/repo',
        'pull_request_number' => 42,
        'pull_request_title' => 'Improve queue routing',
        'pull_request_body' => 'Adds shared dispatch action',
        'base_branch' => 'main',
        'head_branch' => 'feature/review-dispatch',
        'head_sha' => 'abc123',
        'sender_login' => 'sender-user',
    ])->and($payload['author'])->toBe([
        'login' => 'octocat',
        'avatar_url' => 'https://example.com/avatar.png',
    ])->and($payload['assignees'])->toBe([
        ['login' => 'maintainer', 'avatar_url' => 'https://example.com/m.png'],
    ])->and($payload['reviewers'])->toBe([
        ['login' => 'reviewer', 'avatar_url' => null],
    ])->and($payload['labels'])->toBe([
        ['name' => 'enhancement', 'color' => '00ff00'],
    ]);
});

it('normalizes missing and invalid pull request fields safely', function (): void {
    $repository = new Repository([
        'github_id' => 77,
        'full_name' => 'acme/repo',
    ]);

    $payload = app(ManualPullRequestPayloadFactory::class)->make(
        repository: $repository,
        installationId: 99,
        pullRequestData: [
            'number' => '12',
            'body' => ['invalid-body-type'],
            'user' => ['login' => 42, 'avatar_url' => false],
            'assignees' => [
                ['login' => 1, 'avatar_url' => 2],
            ],
            'requested_reviewers' => [],
            'labels' => [
                ['name' => 10, 'color' => 20],
            ],
        ],
        senderLogin: 'sender',
    );

    expect($payload['pull_request_number'])->toBe(12)
        ->and($payload['pull_request_title'])->toBe('')
        ->and($payload['pull_request_body'])->toBeNull()
        ->and($payload['base_branch'])->toBe('')
        ->and($payload['head_branch'])->toBe('')
        ->and($payload['head_sha'])->toBe('')
        ->and($payload['author'])->toBe([
            'login' => '42',
            'avatar_url' => null,
        ])->and($payload['assignees'])->toBe([
            ['login' => '1', 'avatar_url' => null],
        ])->and($payload['labels'])->toBe([
            ['name' => '10', 'color' => '20'],
        ]);
});
