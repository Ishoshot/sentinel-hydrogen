<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;
use App\Services\Context\Filters\SensitiveDataFilter;

it('redacts Stripe keys in file patches', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'config.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => '$stripeKey = "sk_live_1234567890abcdefghijklmnop";',
            ],
        ],
    );

    $filter = new SensitiveDataFilter();
    $filter->filter($bag);

    expect($bag->files[0]['patch'])->toContain('[REDACTED:')
        ->and($bag->files[0]['patch'])->not->toContain('sk_live_1234567890');
});

it('redacts AWS access keys', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'aws.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => '$key = "AKIAIOSFODNN7EXAMPLE";',
            ],
        ],
    );

    $filter = new SensitiveDataFilter();
    $filter->filter($bag);

    expect($bag->files[0]['patch'])->toContain('[REDACTED:aws_access_key:')
        ->and($bag->files[0]['patch'])->not->toContain('AKIAIOSFODNN7EXAMPLE');
});

it('redacts GitHub tokens', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'github.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => 'GITHUB_TOKEN=ghp_1234567890abcdefghijklmnopqrstuvwxyz',
            ],
        ],
    );

    $filter = new SensitiveDataFilter();
    $filter->filter($bag);

    expect($bag->files[0]['patch'])->toContain('[REDACTED:')
        ->and($bag->files[0]['patch'])->not->toContain('ghp_1234567890');
});

it('redacts JWT tokens', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'auth.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => '$jwt = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U";',
            ],
        ],
    );

    $filter = new SensitiveDataFilter();
    $filter->filter($bag);

    expect($bag->files[0]['patch'])->toContain('[REDACTED:jwt:')
        ->and($bag->files[0]['patch'])->not->toContain('eyJhbGciOiJIUzI1NiI');
});

it('completely redacts .env file patches', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => '.env',
                'status' => 'modified',
                'additions' => 5,
                'deletions' => 0,
                'changes' => 5,
                'patch' => "APP_KEY=base64:abc123\nDB_PASSWORD=secret\nAWS_KEY=mykey",
            ],
        ],
    );

    $filter = new SensitiveDataFilter();
    $filter->filter($bag);

    expect($bag->files[0]['patch'])->toBe('[REDACTED - sensitive file]');
});

it('completely redacts .env.local file patches', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => '.env.local',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => 'SECRET=value',
            ],
        ],
    );

    $filter = new SensitiveDataFilter();
    $filter->filter($bag);

    expect($bag->files[0]['patch'])->toBe('[REDACTED - sensitive file]');
});

it('redacts sensitive data in PR body', function (): void {
    $bag = new ContextBag(
        pullRequest: [
            'body' => 'Here is my API key: api_key=abcdefghijklmnopqrstuvwxyz',
        ],
    );

    $filter = new SensitiveDataFilter();
    $filter->filter($bag);

    expect($bag->pullRequest['body'])->toContain('[REDACTED:')
        ->and($bag->pullRequest['body'])->not->toContain('abcdefghijklmnopqrstuvwxyz');
});

it('redacts sensitive data in linked issue comments', function (): void {
    $bag = new ContextBag(
        linkedIssues: [
            [
                'number' => 1,
                'title' => 'Issue',
                'body' => null,
                'state' => 'open',
                'labels' => [],
                'comments' => [
                    [
                        'author' => 'user',
                        'body' => 'Use this token: ghp_secrettoken1234567890abcdefghij',
                    ],
                ],
            ],
        ],
    );

    $filter = new SensitiveDataFilter();
    $filter->filter($bag);

    expect($bag->linkedIssues[0]['comments'][0]['body'])->toContain('[REDACTED:')
        ->and($bag->linkedIssues[0]['comments'][0]['body'])->not->toContain('ghp_secrettoken');
});

it('redacts sensitive data in PR comments', function (): void {
    $bag = new ContextBag(
        prComments: [
            [
                'author' => 'user',
                'body' => 'My password=supersecretpassword123',
                'created_at' => '2024-01-01',
            ],
        ],
    );

    $filter = new SensitiveDataFilter();
    $filter->filter($bag);

    expect($bag->prComments[0]['body'])->toContain('[REDACTED:')
        ->and($bag->prComments[0]['body'])->not->toContain('supersecretpassword');
});

it('does not modify non-sensitive content', function (): void {
    $originalPatch = 'function hello() { return "world"; }';

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'hello.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => $originalPatch,
            ],
        ],
    );

    $filter = new SensitiveDataFilter();
    $filter->filter($bag);

    expect($bag->files[0]['patch'])->toBe($originalPatch);
});

it('has correct order', function (): void {
    $filter = new SensitiveDataFilter();

    expect($filter->order())->toBe(30);
});

it('has correct name', function (): void {
    $filter = new SensitiveDataFilter();

    expect($filter->name())->toBe('sensitive_data');
});
