<?php

declare(strict_types=1);

use App\Services\GitHub\Factories\GitHubApiPayloadFactory;

beforeEach(function (): void {
    $this->factory = new GitHubApiPayloadFactory;
});

it('builds pull request review payload', function (): void {
    $result = $this->factory->pullRequestReview(
        body: 'LGTM',
        event: 'APPROVE',
        comments: [],
        commitId: 'abc123',
    );

    expect($result)->toBe([
        'body' => 'LGTM',
        'event' => 'APPROVE',
        'commit_id' => 'abc123',
    ]);
});

it('builds pull request review payload with comments', function (): void {
    $comments = [
        ['path' => 'src/app.php', 'line' => 10, 'side' => 'RIGHT', 'body' => 'Fix this'],
    ];

    $result = $this->factory->pullRequestReview(
        body: 'Changes needed',
        event: 'REQUEST_CHANGES',
        comments: $comments,
        commitId: null,
    );

    expect($result)->toBe([
        'body' => 'Changes needed',
        'event' => 'REQUEST_CHANGES',
        'comments' => $comments,
    ]);
});

it('builds check run payload', function (): void {
    $result = $this->factory->checkRun(
        name: 'Sentinel Review',
        headSha: 'abc123',
        status: 'completed',
        conclusion: 'success',
        summary: 'All checks passed',
        annotations: [],
    );

    expect($result['name'])->toBe('Sentinel Review');
    expect($result['head_sha'])->toBe('abc123');
    expect($result['status'])->toBe('completed');
    expect($result['conclusion'])->toBe('success');
    expect($result['output']['title'])->toBe('Sentinel Review');
    expect($result['output']['summary'])->toBe('All checks passed');
});

it('builds check run payload with annotations', function (): void {
    $annotations = [
        ['path' => 'src/app.php', 'start_line' => 1, 'end_line' => 5, 'annotation_level' => 'warning', 'message' => 'Issue found'],
    ];

    $result = $this->factory->checkRun(
        name: 'Sentinel Review',
        headSha: 'abc123',
        status: 'completed',
        conclusion: 'neutral',
        summary: 'Issues found',
        annotations: $annotations,
    );

    expect($result['output']['annotations'])->toBe($annotations);
});

it('builds in-progress check run without conclusion', function (): void {
    $result = $this->factory->checkRun(
        name: 'Sentinel Review',
        headSha: 'abc123',
        status: 'in_progress',
        conclusion: null,
        summary: null,
        annotations: [],
    );

    expect($result)->toBe([
        'name' => 'Sentinel Review',
        'head_sha' => 'abc123',
        'status' => 'in_progress',
    ]);
});
