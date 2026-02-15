<?php

declare(strict_types=1);

use App\Services\Context\Collectors\Support\DiffPullRequestDataExtractor;
use App\Support\MetadataExtractor;

it('extracts pull request data from metadata', function (): void {
    $extractor = new DiffPullRequestDataExtractor;
    $metadata = MetadataExtractor::from([
        'pull_request_number' => 42,
        'pull_request_title' => 'Fix bug',
        'pull_request_body' => 'This fixes a critical bug',
        'base_branch' => 'main',
        'head_branch' => 'fix/bug',
        'head_sha' => 'abc123',
        'sender_login' => 'developer',
        'is_draft' => false,
        'author' => ['login' => 'developer', 'avatar_url' => 'https://example.com/avatar.png'],
        'assignees' => [],
        'reviewers' => [],
        'labels' => [],
    ]);

    $result = $extractor->extract($metadata, 'owner/repo');

    expect($result['number'])->toBe(42);
    expect($result['title'])->toBe('Fix bug');
    expect($result['body'])->toBe('This fixes a critical bug');
    expect($result['base_branch'])->toBe('main');
    expect($result['head_branch'])->toBe('fix/bug');
    expect($result['head_sha'])->toBe('abc123');
    expect($result['sender_login'])->toBe('developer');
    expect($result['repository_full_name'])->toBe('owner/repo');
    expect($result['is_draft'])->toBeFalse();
});
