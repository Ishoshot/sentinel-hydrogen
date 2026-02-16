<?php

declare(strict_types=1);

use App\Services\CodeIndexing\Strategies\HybridSearchResultMergeStrategy;

it('merges keyword and semantic results into hybrid results', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $keywordResults = [
        ['file_path' => 'app/Models/User.php', 'content' => 'class User', 'score' => 0.9, 'metadata' => ['line' => 1]],
    ];
    $semanticResults = [
        ['file_path' => 'app/Models/User.php', 'content' => 'class User', 'score' => 0.8, 'metadata' => ['line' => 1]],
    ];

    $results = $strategy->merge($keywordResults, $semanticResults, 10);

    expect($results)->toHaveCount(1)
        ->and($results[0]['match_type'])->toBe('hybrid')
        ->and($results[0]['file_path'])->toBe('app/Models/User.php')
        ->and($results[0]['score'])->toBe((0.9 * 0.6) + (0.8 * 0.4));
});

it('marks keyword-only results correctly', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $keywordResults = [
        ['file_path' => 'app/Models/User.php', 'content' => 'class User', 'score' => 0.9, 'metadata' => []],
    ];

    $results = $strategy->merge($keywordResults, [], 10);

    expect($results)->toHaveCount(1)
        ->and($results[0]['match_type'])->toBe('keyword')
        ->and($results[0]['score'])->toBe(0.9 * 0.6);
});

it('marks semantic-only results correctly', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $semanticResults = [
        ['file_path' => 'app/Models/Post.php', 'content' => 'class Post', 'score' => 0.85, 'metadata' => []],
    ];

    $results = $strategy->merge([], $semanticResults, 10);

    expect($results)->toHaveCount(1)
        ->and($results[0]['match_type'])->toBe('semantic')
        ->and($results[0]['score'])->toBe(0.85 * 0.4);
});

it('deduplicates results by file path and content hash', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $content = 'class User extends Model';
    $keywordResults = [
        ['file_path' => 'app/Models/User.php', 'content' => $content, 'score' => 0.9, 'metadata' => []],
    ];
    $semanticResults = [
        ['file_path' => 'app/Models/User.php', 'content' => $content, 'score' => 0.7, 'metadata' => []],
    ];

    $results = $strategy->merge($keywordResults, $semanticResults, 10);

    expect($results)->toHaveCount(1);
});

it('keeps separate entries for different content in same file', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $keywordResults = [
        ['file_path' => 'app/Models/User.php', 'content' => 'class User', 'score' => 0.9, 'metadata' => []],
    ];
    $semanticResults = [
        ['file_path' => 'app/Models/User.php', 'content' => 'public function posts()', 'score' => 0.7, 'metadata' => []],
    ];

    $results = $strategy->merge($keywordResults, $semanticResults, 10);

    expect($results)->toHaveCount(2);
});

it('sorts results by combined score descending', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $keywordResults = [
        ['file_path' => 'low.php', 'content' => 'low', 'score' => 0.3, 'metadata' => []],
        ['file_path' => 'high.php', 'content' => 'high', 'score' => 0.95, 'metadata' => []],
    ];
    $semanticResults = [
        ['file_path' => 'mid.php', 'content' => 'mid', 'score' => 0.6, 'metadata' => []],
    ];

    $results = $strategy->merge($keywordResults, $semanticResults, 10);

    expect($results[0]['file_path'])->toBe('high.php')
        ->and($results[0]['score'])->toBeGreaterThan($results[1]['score']);
});

it('respects the limit parameter', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $keywordResults = [
        ['file_path' => 'a.php', 'content' => 'a', 'score' => 0.9, 'metadata' => []],
        ['file_path' => 'b.php', 'content' => 'b', 'score' => 0.8, 'metadata' => []],
        ['file_path' => 'c.php', 'content' => 'c', 'score' => 0.7, 'metadata' => []],
    ];

    $results = $strategy->merge($keywordResults, [], 2);

    expect($results)->toHaveCount(2);
});

it('handles empty inputs gracefully', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $results = $strategy->merge([], [], 10);

    expect($results)->toBe([]);
});

it('handles missing score by defaulting to zero', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $keywordResults = [
        ['file_path' => 'a.php', 'content' => 'a', 'metadata' => []],
    ];

    $results = $strategy->merge($keywordResults, [], 10);

    expect($results)->toHaveCount(1)
        ->and($results[0]['score'])->toBe(0.0);
});

it('handles non-numeric score by defaulting to zero', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $keywordResults = [
        ['file_path' => 'a.php', 'content' => 'a', 'score' => 'not-a-number', 'metadata' => []],
    ];

    $results = $strategy->merge($keywordResults, [], 10);

    expect($results)->toHaveCount(1)
        ->and($results[0]['score'])->toBe(0.0);
});

it('handles missing file_path and content by defaulting to empty strings', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $keywordResults = [
        ['score' => 0.5, 'metadata' => []],
    ];

    $results = $strategy->merge($keywordResults, [], 10);

    expect($results)->toHaveCount(1)
        ->and($results[0]['file_path'])->toBe('')
        ->and($results[0]['content'])->toBe('');
});

it('preserves metadata from keyword results', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $keywordResults = [
        ['file_path' => 'a.php', 'content' => 'code', 'score' => 0.9, 'metadata' => ['language' => 'php', 'line' => 42]],
    ];

    $results = $strategy->merge($keywordResults, [], 10);

    expect($results[0]['metadata'])->toBe(['language' => 'php', 'line' => 42]);
});

it('handles null metadata by defaulting to empty array', function (): void {
    $strategy = new HybridSearchResultMergeStrategy;

    $keywordResults = [
        ['file_path' => 'a.php', 'content' => 'code', 'score' => 0.9, 'metadata' => null],
    ];

    $results = $strategy->merge($keywordResults, [], 10);

    expect($results[0]['metadata'])->toBe([]);
});
