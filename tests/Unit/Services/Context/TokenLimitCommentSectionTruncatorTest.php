<?php

declare(strict_types=1);

use App\Services\Context\Filters\Support\AbstractTokenTruncator;
use App\Services\Context\Filters\Support\TokenLimitCommentSectionTruncator;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;

beforeEach(function (): void {
    $this->tokenTruncator = new AbstractTokenTruncator(new HeuristicTokenCounter);
    $this->truncator = new TokenLimitCommentSectionTruncator($this->tokenTruncator);
});

it('returns empty array when given empty comments', function (): void {
    $result = $this->truncator->truncate([], 1000);

    expect($result)->toBe([]);
});

it('returns all comments when within budget', function (): void {
    $comments = [
        ['author' => 'alice', 'body' => 'Looks good', 'created_at' => '2026-01-01T00:00:00Z'],
        ['author' => 'bob', 'body' => 'LGTM', 'created_at' => '2026-01-01T01:00:00Z'],
    ];

    $result = $this->truncator->truncate($comments, 1000);

    expect($result)->toHaveCount(2)
        ->and($result[0]['author'])->toBe('alice')
        ->and($result[1]['author'])->toBe('bob');
});

it('truncates comments that exceed the token budget', function (): void {
    // HeuristicTokenCounter: ceil(strlen * 0.25) tokens
    // A 100-char body ~ 25 tokens
    $comments = [
        ['author' => 'alice', 'body' => str_repeat('a', 100), 'created_at' => '2026-01-01T00:00:00Z'],
        ['author' => 'bob', 'body' => str_repeat('b', 100), 'created_at' => '2026-01-01T01:00:00Z'],
        ['author' => 'carol', 'body' => str_repeat('c', 100), 'created_at' => '2026-01-01T02:00:00Z'],
    ];

    // Budget of 40 tokens: only first comment (25 tokens) fits; second would push to 50
    $result = $this->truncator->truncate($comments, 40);

    expect($result)->toHaveCount(1)
        ->and($result[0]['author'])->toBe('alice');
});

it('includes comments up to the exact budget boundary', function (): void {
    $comments = [
        ['author' => 'alice', 'body' => str_repeat('a', 40), 'created_at' => '2026-01-01T00:00:00Z'],
        ['author' => 'bob', 'body' => str_repeat('b', 40), 'created_at' => '2026-01-01T01:00:00Z'],
    ];

    // 40 chars = 10 tokens each, budget of 20 should include both
    $result = $this->truncator->truncate($comments, 20);

    expect($result)->toHaveCount(2);
});

it('stops before adding a comment that would exceed the budget', function (): void {
    $comments = [
        ['author' => 'alice', 'body' => str_repeat('a', 40), 'created_at' => '2026-01-01T00:00:00Z'],
        ['author' => 'bob', 'body' => str_repeat('b', 40), 'created_at' => '2026-01-01T01:00:00Z'],
    ];

    // 40 chars = 10 tokens each, budget of 19 should include only first
    $result = $this->truncator->truncate($comments, 19);

    expect($result)->toHaveCount(1)
        ->and($result[0]['author'])->toBe('alice');
});

it('returns no comments when budget is zero', function (): void {
    $comments = [
        ['author' => 'alice', 'body' => 'Hello', 'created_at' => '2026-01-01T00:00:00Z'],
    ];

    $result = $this->truncator->truncate($comments, 0);

    expect($result)->toBe([]);
});

it('handles a single large comment exceeding the budget', function (): void {
    $comments = [
        ['author' => 'alice', 'body' => str_repeat('x', 1000), 'created_at' => '2026-01-01T00:00:00Z'],
    ];

    // 1000 chars = 250 tokens, budget of 100 means the first comment already exceeds
    $result = $this->truncator->truncate($comments, 100);

    expect($result)->toBe([]);
});

it('preserves comment structure in returned results', function (): void {
    $comments = [
        ['author' => 'alice', 'body' => 'Great PR!', 'created_at' => '2026-01-15T10:30:00Z'],
    ];

    $result = $this->truncator->truncate($comments, 1000);

    expect($result[0])->toBe([
        'author' => 'alice',
        'body' => 'Great PR!',
        'created_at' => '2026-01-15T10:30:00Z',
    ]);
});
