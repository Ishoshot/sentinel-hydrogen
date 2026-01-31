<?php

declare(strict_types=1);

use App\Services\Reviews\ValueObjects\GitHubLabel;
use App\Services\Reviews\ValueObjects\GitHubUser;
use App\Services\Reviews\ValueObjects\PullRequestInfo;

it('can be constructed with all parameters', function (): void {
    $author = new GitHubUser('author', 'https://avatar.url');
    $assignee = new GitHubUser('assignee', null);
    $reviewer = new GitHubUser('reviewer', null);
    $label = new GitHubLabel('bug', 'ff0000');

    $info = new PullRequestInfo(
        number: 42,
        title: 'Fix bug',
        body: 'This PR fixes a bug',
        baseBranch: 'main',
        headBranch: 'fix/bug',
        headSha: 'abc123',
        senderLogin: 'author',
        repositoryFullName: 'owner/repo',
        author: $author,
        isDraft: false,
        assignees: [$assignee],
        reviewers: [$reviewer],
        labels: [$label],
    );

    expect($info->number)->toBe(42);
    expect($info->title)->toBe('Fix bug');
    expect($info->body)->toBe('This PR fixes a bug');
    expect($info->baseBranch)->toBe('main');
    expect($info->headBranch)->toBe('fix/bug');
    expect($info->headSha)->toBe('abc123');
    expect($info->senderLogin)->toBe('author');
    expect($info->repositoryFullName)->toBe('owner/repo');
    expect($info->author)->toBe($author);
    expect($info->isDraft)->toBeFalse();
    expect($info->assignees)->toHaveCount(1);
    expect($info->reviewers)->toHaveCount(1);
    expect($info->labels)->toHaveCount(1);
});

it('can be constructed with empty arrays', function (): void {
    $author = new GitHubUser('author', null);

    $info = new PullRequestInfo(
        number: 1,
        title: 'Test',
        body: null,
        baseBranch: 'main',
        headBranch: 'test',
        headSha: 'def456',
        senderLogin: 'author',
        repositoryFullName: 'owner/repo',
        author: $author,
        isDraft: true,
    );

    expect($info->body)->toBeNull();
    expect($info->isDraft)->toBeTrue();
    expect($info->assignees)->toBe([]);
    expect($info->reviewers)->toBe([]);
    expect($info->labels)->toBe([]);
});

it('can be created from array', function (): void {
    $data = [
        'number' => 42,
        'title' => 'Add feature',
        'body' => 'Feature description',
        'base_branch' => 'main',
        'head_branch' => 'feature/new',
        'head_sha' => 'sha123',
        'sender_login' => 'developer',
        'repository_full_name' => 'org/project',
        'author' => [
            'login' => 'developer',
            'avatar_url' => 'https://avatar.url',
        ],
        'is_draft' => false,
        'assignees' => [
            ['login' => 'assignee1', 'avatar_url' => null],
        ],
        'reviewers' => [
            ['login' => 'reviewer1', 'avatar_url' => 'https://reviewer.avatar'],
        ],
        'labels' => [
            ['name' => 'enhancement', 'color' => '00ff00'],
        ],
    ];

    $info = PullRequestInfo::fromArray($data);

    expect($info->number)->toBe(42);
    expect($info->title)->toBe('Add feature');
    expect($info->author->login)->toBe('developer');
    expect($info->assignees)->toHaveCount(1);
    expect($info->reviewers)->toHaveCount(1);
    expect($info->labels)->toHaveCount(1);
});

it('converts to array correctly', function (): void {
    $author = new GitHubUser('author', 'https://avatar.url');
    $label = new GitHubLabel('bug', 'ff0000');

    $info = new PullRequestInfo(
        number: 42,
        title: 'Fix bug',
        body: 'Description',
        baseBranch: 'main',
        headBranch: 'fix/bug',
        headSha: 'abc123',
        senderLogin: 'author',
        repositoryFullName: 'owner/repo',
        author: $author,
        isDraft: false,
        assignees: [],
        reviewers: [],
        labels: [$label],
    );

    $array = $info->toArray();

    expect($array['number'])->toBe(42);
    expect($array['title'])->toBe('Fix bug');
    expect($array['body'])->toBe('Description');
    expect($array['base_branch'])->toBe('main');
    expect($array['head_branch'])->toBe('fix/bug');
    expect($array['head_sha'])->toBe('abc123');
    expect($array['sender_login'])->toBe('author');
    expect($array['repository_full_name'])->toBe('owner/repo');
    expect($array['author'])->toBe(['login' => 'author', 'avatar_url' => 'https://avatar.url']);
    expect($array['is_draft'])->toBeFalse();
    expect($array['assignees'])->toBe([]);
    expect($array['reviewers'])->toBe([]);
    expect($array['labels'])->toBe([['name' => 'bug', 'color' => 'ff0000']]);
});
