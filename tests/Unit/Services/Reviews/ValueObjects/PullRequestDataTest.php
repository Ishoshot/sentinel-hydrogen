<?php

declare(strict_types=1);

use App\Services\Reviews\ValueObjects\GitHubUser;
use App\Services\Reviews\ValueObjects\PullRequestData;
use App\Services\Reviews\ValueObjects\PullRequestFile;
use App\Services\Reviews\ValueObjects\PullRequestInfo;
use App\Services\Reviews\ValueObjects\PullRequestMetrics;

it('can be constructed with all parameters', function (): void {
    $author = new GitHubUser('author', null);
    $info = new PullRequestInfo(
        number: 42,
        title: 'Test PR',
        body: null,
        baseBranch: 'main',
        headBranch: 'feature',
        headSha: 'abc123',
        senderLogin: 'author',
        repositoryFullName: 'owner/repo',
        author: $author,
        isDraft: false,
    );
    $file = new PullRequestFile('test.php', 10, 5, 15);
    $metrics = new PullRequestMetrics(1, 10, 5);

    $data = new PullRequestData(
        pullRequest: $info,
        files: [$file],
        metrics: $metrics,
    );

    expect($data->pullRequest)->toBe($info);
    expect($data->files)->toHaveCount(1);
    expect($data->metrics)->toBe($metrics);
});

it('returns pr number via helper method', function (): void {
    $author = new GitHubUser('author', null);
    $info = new PullRequestInfo(
        number: 99,
        title: 'Test',
        body: null,
        baseBranch: 'main',
        headBranch: 'test',
        headSha: 'sha',
        senderLogin: 'author',
        repositoryFullName: 'owner/repo',
        author: $author,
        isDraft: false,
    );
    $metrics = new PullRequestMetrics(0, 0, 0);

    $data = new PullRequestData(
        pullRequest: $info,
        files: [],
        metrics: $metrics,
    );

    expect($data->prNumber())->toBe(99);
});

it('returns is draft via helper method', function (): void {
    $author = new GitHubUser('author', null);
    $info = new PullRequestInfo(
        number: 1,
        title: 'Draft PR',
        body: null,
        baseBranch: 'main',
        headBranch: 'draft',
        headSha: 'sha',
        senderLogin: 'author',
        repositoryFullName: 'owner/repo',
        author: $author,
        isDraft: true,
    );
    $metrics = new PullRequestMetrics(0, 0, 0);

    $data = new PullRequestData(
        pullRequest: $info,
        files: [],
        metrics: $metrics,
    );

    expect($data->isDraft())->toBeTrue();
});

it('can be created from array', function (): void {
    $arrayData = [
        'pull_request' => [
            'number' => 42,
            'title' => 'Add feature',
            'body' => 'Description',
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'abc123',
            'sender_login' => 'developer',
            'repository_full_name' => 'org/repo',
            'author' => ['login' => 'developer', 'avatar_url' => null],
            'is_draft' => false,
            'assignees' => [],
            'reviewers' => [],
            'labels' => [],
        ],
        'files' => [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5, 'changes' => 15],
        ],
        'metrics' => [
            'files_changed' => 1,
            'lines_added' => 10,
            'lines_deleted' => 5,
        ],
    ];

    $data = PullRequestData::fromArray($arrayData);

    expect($data->prNumber())->toBe(42);
    expect($data->pullRequest->title)->toBe('Add feature');
    expect($data->files)->toHaveCount(1);
    expect($data->files[0]->filename)->toBe('src/app.php');
    expect($data->metrics->filesChanged)->toBe(1);
});

it('converts to array correctly', function (): void {
    $author = new GitHubUser('author', 'https://avatar.url');
    $info = new PullRequestInfo(
        number: 42,
        title: 'Test',
        body: null,
        baseBranch: 'main',
        headBranch: 'test',
        headSha: 'sha',
        senderLogin: 'author',
        repositoryFullName: 'owner/repo',
        author: $author,
        isDraft: false,
    );
    $file = new PullRequestFile('file.php', 5, 2, 7);
    $metrics = new PullRequestMetrics(1, 5, 2);

    $data = new PullRequestData(
        pullRequest: $info,
        files: [$file],
        metrics: $metrics,
    );

    $array = $data->toArray();

    expect($array['pull_request']['number'])->toBe(42);
    expect($array['files'])->toHaveCount(1);
    expect($array['files'][0]['filename'])->toBe('file.php');
    expect($array['metrics']['files_changed'])->toBe(1);
});
