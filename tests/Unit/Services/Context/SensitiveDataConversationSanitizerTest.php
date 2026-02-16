<?php

declare(strict_types=1);

use App\Services\Context\Filters\Support\SensitiveDataConversationSanitizer;
use App\Services\Context\SensitiveDataRedactor;

beforeEach(function (): void {
    $this->sanitizer = new SensitiveDataConversationSanitizer(new SensitiveDataRedactor);
});

// --- sanitizePullRequest ---

it('sanitizes sensitive data in pull request body', function (): void {
    $redactedCount = 0;
    $pr = [
        'title' => 'Fix auth',
        'body' => 'Set api_key=sk-1234567890abcdefghij for the service',
    ];

    $result = $this->sanitizer->sanitizePullRequest($pr, $redactedCount);

    expect($result['body'])->toContain('[REDACTED:api_key:')
        ->and($result['body'])->not->toContain('sk-1234567890abcdefghij')
        ->and($result['title'])->toBe('Fix auth')
        ->and($redactedCount)->toBe(1);
});

it('does not increment redacted count when body has no sensitive data', function (): void {
    $redactedCount = 0;
    $pr = [
        'title' => 'Update readme',
        'body' => 'This is a normal pull request description.',
    ];

    $result = $this->sanitizer->sanitizePullRequest($pr, $redactedCount);

    expect($result['body'])->toBe('This is a normal pull request description.')
        ->and($redactedCount)->toBe(0);
});

it('handles pull request without body key', function (): void {
    $redactedCount = 0;
    $pr = ['title' => 'No body PR'];

    $result = $this->sanitizer->sanitizePullRequest($pr, $redactedCount);

    expect($result)->toBe(['title' => 'No body PR'])
        ->and($redactedCount)->toBe(0);
});

it('handles pull request with non-string body', function (): void {
    $redactedCount = 0;
    $pr = ['title' => 'Test', 'body' => null];

    $result = $this->sanitizer->sanitizePullRequest($pr, $redactedCount);

    expect($result['body'])->toBeNull()
        ->and($redactedCount)->toBe(0);
});

// --- sanitizeLinkedIssues ---

it('sanitizes sensitive data in linked issue bodies', function (): void {
    $redactedCount = 0;
    $issues = [
        [
            'number' => 1,
            'title' => 'Issue with token',
            'body' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test',
            'state' => 'open',
            'labels' => ['bug'],
            'comments' => [],
        ],
    ];

    $result = $this->sanitizer->sanitizeLinkedIssues($issues, $redactedCount);

    expect($result[0]['body'])->toContain('[REDACTED:')
        ->and($redactedCount)->toBe(1);
});

it('sanitizes sensitive data in linked issue comments', function (): void {
    $redactedCount = 0;
    $issues = [
        [
            'number' => 1,
            'title' => 'Issue',
            'body' => 'Clean body',
            'state' => 'open',
            'labels' => [],
            'comments' => [
                ['author' => 'user1', 'body' => 'api_key=sk-1234567890abcdefghij'],
            ],
        ],
    ];

    $result = $this->sanitizer->sanitizeLinkedIssues($issues, $redactedCount);

    expect($result[0]['comments'][0]['body'])->toContain('[REDACTED:api_key:')
        ->and($redactedCount)->toBe(1);
});

it('sanitizes both issue body and comments', function (): void {
    $redactedCount = 0;
    $issues = [
        [
            'number' => 1,
            'title' => 'Issue',
            'body' => 'password=mysecretpassword1',
            'state' => 'open',
            'labels' => [],
            'comments' => [
                ['author' => 'user1', 'body' => 'api_key=sk-1234567890abcdefghij'],
            ],
        ],
    ];

    $result = $this->sanitizer->sanitizeLinkedIssues($issues, $redactedCount);

    expect($result[0]['body'])->toContain('[REDACTED:')
        ->and($result[0]['comments'][0]['body'])->toContain('[REDACTED:')
        ->and($redactedCount)->toBe(2);
});

it('skips sanitization of null issue body', function (): void {
    $redactedCount = 0;
    $issues = [
        [
            'number' => 1,
            'title' => 'Issue',
            'body' => null,
            'state' => 'open',
            'labels' => [],
            'comments' => [],
        ],
    ];

    $result = $this->sanitizer->sanitizeLinkedIssues($issues, $redactedCount);

    expect($result[0]['body'])->toBeNull()
        ->and($redactedCount)->toBe(0);
});

it('handles empty linked issues array', function (): void {
    $redactedCount = 0;

    $result = $this->sanitizer->sanitizeLinkedIssues([], $redactedCount);

    expect($result)->toBe([])
        ->and($redactedCount)->toBe(0);
});

// --- sanitizePrComments ---

it('sanitizes sensitive data in pr comments', function (): void {
    $redactedCount = 0;
    $comments = [
        ['author' => 'dev', 'body' => 'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'created_at' => '2025-01-01'],
    ];

    $result = $this->sanitizer->sanitizePrComments($comments, $redactedCount);

    expect($result[0]['body'])->toContain('[REDACTED:github_token:')
        ->and($result[0]['author'])->toBe('dev')
        ->and($result[0]['created_at'])->toBe('2025-01-01')
        ->and($redactedCount)->toBe(1);
});

it('does not increment count for clean pr comments', function (): void {
    $redactedCount = 0;
    $comments = [
        ['author' => 'dev', 'body' => 'LGTM, looks good!', 'created_at' => '2025-01-01'],
    ];

    $result = $this->sanitizer->sanitizePrComments($comments, $redactedCount);

    expect($result[0]['body'])->toBe('LGTM, looks good!')
        ->and($redactedCount)->toBe(0);
});

it('sanitizes multiple pr comments', function (): void {
    $redactedCount = 0;
    $comments = [
        ['author' => 'dev1', 'body' => 'api_key=sk-1234567890abcdefghij', 'created_at' => '2025-01-01'],
        ['author' => 'dev2', 'body' => 'Clean comment', 'created_at' => '2025-01-02'],
        ['author' => 'dev3', 'body' => 'password=supersecretpassword123', 'created_at' => '2025-01-03'],
    ];

    $result = $this->sanitizer->sanitizePrComments($comments, $redactedCount);

    expect($redactedCount)->toBe(2)
        ->and($result[1]['body'])->toBe('Clean comment');
});

it('handles empty pr comments array', function (): void {
    $redactedCount = 0;

    $result = $this->sanitizer->sanitizePrComments([], $redactedCount);

    expect($result)->toBe([])
        ->and($redactedCount)->toBe(0);
});
