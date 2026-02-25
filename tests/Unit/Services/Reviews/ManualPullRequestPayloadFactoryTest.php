<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;
use App\Services\Reviews\ManualPullRequestPayloadFactory;
use App\Services\Reviews\ValueObjects\GitHubLabel;
use App\Services\Reviews\ValueObjects\GitHubUser;

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

    expect($payload)->toBeInstanceOf(PullRequestWebhookPayload::class)
        ->and($payload->action)->toBe('manual_trigger')
        ->and($payload->installationId)->toBe(123456)
        ->and($payload->repositoryId)->toBe(987654)
        ->and($payload->repositoryFullName)->toBe('acme/repo')
        ->and($payload->pullRequestNumber)->toBe(42)
        ->and($payload->pullRequestTitle)->toBe('Improve queue routing')
        ->and($payload->pullRequestBody)->toBe('Adds shared dispatch action')
        ->and($payload->baseBranch)->toBe('main')
        ->and($payload->headBranch)->toBe('feature/review-dispatch')
        ->and($payload->headSha)->toBe('abc123')
        ->and($payload->senderLogin)->toBe('sender-user')
        ->and($payload->author)->toBeInstanceOf(GitHubUser::class)
        ->and($payload->author->login)->toBe('octocat')
        ->and($payload->author->avatarUrl)->toBe('https://example.com/avatar.png')
        ->and($payload->assignees)->toHaveCount(1)
        ->and($payload->assignees[0])->toBeInstanceOf(GitHubUser::class)
        ->and($payload->assignees[0]->login)->toBe('maintainer')
        ->and($payload->assignees[0]->avatarUrl)->toBe('https://example.com/m.png')
        ->and($payload->reviewers)->toHaveCount(1)
        ->and($payload->reviewers[0])->toBeInstanceOf(GitHubUser::class)
        ->and($payload->reviewers[0]->login)->toBe('reviewer')
        ->and($payload->reviewers[0]->avatarUrl)->toBeNull()
        ->and($payload->labels)->toHaveCount(1)
        ->and($payload->labels[0])->toBeInstanceOf(GitHubLabel::class)
        ->and($payload->labels[0]->name)->toBe('enhancement')
        ->and($payload->labels[0]->color)->toBe('00ff00');
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

    expect($payload->pullRequestNumber)->toBe(12)
        ->and($payload->pullRequestTitle)->toBe('')
        ->and($payload->pullRequestBody)->toBeNull()
        ->and($payload->baseBranch)->toBe('')
        ->and($payload->headBranch)->toBe('')
        ->and($payload->headSha)->toBe('')
        ->and($payload->author)->toBeInstanceOf(GitHubUser::class)
        ->and($payload->author->login)->toBe('42')
        ->and($payload->author->avatarUrl)->toBeNull()
        ->and($payload->assignees)->toHaveCount(1)
        ->and($payload->assignees[0])->toBeInstanceOf(GitHubUser::class)
        ->and($payload->assignees[0]->login)->toBe('1')
        ->and($payload->assignees[0]->avatarUrl)->toBeNull()
        ->and($payload->labels)->toHaveCount(1)
        ->and($payload->labels[0])->toBeInstanceOf(GitHubLabel::class)
        ->and($payload->labels[0]->name)->toBe('10')
        ->and($payload->labels[0]->color)->toBe('20');
});
