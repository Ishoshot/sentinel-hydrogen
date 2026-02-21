<?php

declare(strict_types=1);

use App\Services\Commands\Resolvers\IssueLinkedReferencesResolver;

it('extracts linked pull requests and commits from issue text comments and timeline', function (): void {
    $resolver = new IssueLinkedReferencesResolver();

    $issue = [
        'body' => 'Investigate fix from https://github.com/acme/sentinel/pull/44 and commit https://github.com/acme/sentinel/commit/abc1234def5678',
    ];

    $comments = [
        ['body' => 'Related PR: https://github.com/acme/sentinel/pull/55'],
        ['body' => 'No links in this comment'],
    ];

    $timeline = [
        [
            'event' => 'cross-referenced',
            'source' => [
                'issue' => [
                    'number' => 77,
                    'title' => 'Queue timeout hardening',
                    'state' => 'open',
                    'pull_request' => ['url' => 'https://api.github.com/repos/acme/sentinel/pulls/77'],
                ],
            ],
        ],
        [
            'event' => 'referenced',
            'commit_id' => 'ff1122aa3344bb55cc66dd77ee88ff99aa00bb11',
        ],
    ];

    $resolved = $resolver->resolve($issue, $comments, $timeline);

    expect($resolved['pull_requests'])->toHaveCount(3)
        ->and($resolved['commits'])->toHaveCount(2)
        ->and($resolved['pull_requests'][0])->toHaveKey('number')
        ->and($resolved['commits'][0])->toHaveKey('sha');

    $prNumbers = array_map(static fn (array $pr): int => $pr['number'], $resolved['pull_requests']);
    $commitShas = array_map(static fn (array $commit): string => $commit['sha'], $resolved['commits']);

    expect($prNumbers)->toContain(44, 55, 77)
        ->and($commitShas)->toContain('abc1234def5678', 'ff1122aa3344bb55cc66dd77ee88ff99aa00bb11');
});

it('returns empty arrays when no references exist', function (): void {
    $resolver = new IssueLinkedReferencesResolver();

    $resolved = $resolver->resolve(
        ['body' => 'General discussion'],
        [['body' => 'Still investigating']],
        [['event' => 'assigned']]
    );

    expect($resolved['pull_requests'])->toBe([])
        ->and($resolved['commits'])->toBe([]);
});
