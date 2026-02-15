<?php

declare(strict_types=1);

use App\Services\Context\Filters\Support\AbstractTokenTruncator;
use App\Services\Context\Filters\Support\TokenLimitSemanticDataTruncator;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;

beforeEach(function (): void {
    $this->tokenTruncator = new AbstractTokenTruncator(new HeuristicTokenCounter);
    $this->truncator = new TokenLimitSemanticDataTruncator($this->tokenTruncator);
});

it('returns empty array for empty data', function (): void {
    $result = $this->truncator->truncate([], 1000);

    expect($result)->toBe([]);
});

it('preserves language field', function (): void {
    $data = ['language' => 'php'];

    $result = $this->truncator->truncate($data, 1000);

    expect($result['language'])->toBe('php');
});

it('limits functions to 5 entries', function (): void {
    $data = [
        'language' => 'php',
        'functions' => ['fn1', 'fn2', 'fn3', 'fn4', 'fn5', 'fn6', 'fn7'],
    ];

    $result = $this->truncator->truncate($data, 10000);

    expect($result['functions'])->toHaveCount(5);
});

it('limits imports to 5 entries', function (): void {
    $data = [
        'language' => 'php',
        'imports' => ['imp1', 'imp2', 'imp3', 'imp4', 'imp5', 'imp6', 'imp7', 'imp8'],
    ];

    $result = $this->truncator->truncate($data, 10000);

    expect($result['imports'])->toHaveCount(5);
});

it('limits classes to 3 entries', function (): void {
    $data = [
        'language' => 'php',
        'classes' => [
            ['name' => 'A', 'methods' => ['m1']],
            ['name' => 'B', 'methods' => ['m1']],
            ['name' => 'C', 'methods' => ['m1']],
            ['name' => 'D', 'methods' => ['m1']],
            ['name' => 'E', 'methods' => ['m1']],
        ],
    ];

    $result = $this->truncator->truncate($data, 10000);

    expect($result['classes'])->toHaveCount(3);
});

it('limits methods within each class to 5', function (): void {
    $data = [
        'language' => 'php',
        'classes' => [
            ['name' => 'BigClass', 'methods' => ['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8']],
        ],
    ];

    $result = $this->truncator->truncate($data, 10000);

    expect($result['classes'][0]['methods'])->toHaveCount(5);
});

it('filters out non-array class entries', function (): void {
    $data = [
        'language' => 'php',
        'classes' => [
            'not_an_array',
            ['name' => 'ValidClass', 'methods' => ['m1']],
            42,
        ],
    ];

    $result = $this->truncator->truncate($data, 10000);

    // Only the valid array entry should remain
    expect($result['classes'])->toHaveCount(1)
        ->and($result['classes'][0]['name'])->toBe('ValidClass');
});

it('applies aggressive fallback when result exceeds token budget', function (): void {
    // Create data large enough that the initial truncation still exceeds budget
    $data = [
        'language' => 'php',
        'functions' => ['fn1', 'fn2', 'fn3', 'fn4', 'fn5'],
        'classes' => [
            ['name' => 'A', 'methods' => ['m1', 'm2', 'm3', 'm4', 'm5']],
            ['name' => 'B', 'methods' => ['m1', 'm2', 'm3', 'm4', 'm5']],
            ['name' => 'C', 'methods' => ['m1', 'm2', 'm3', 'm4', 'm5']],
        ],
        'imports' => ['imp1', 'imp2', 'imp3', 'imp4', 'imp5'],
    ];

    // With a very tight budget, the fallback branch should trigger
    $result = $this->truncator->truncate($data, 1);

    expect($result)->toHaveKey('language')
        ->and($result['language'])->toBe('php')
        ->and($result['functions'])->toHaveCount(2)
        ->and($result['classes'])->toHaveCount(1)
        ->and($result)->not->toHaveKey('imports');
});

it('handles classes without methods key', function (): void {
    $data = [
        'language' => 'go',
        'classes' => [
            ['name' => 'NoMethods'],
        ],
    ];

    $result = $this->truncator->truncate($data, 10000);

    expect($result['classes'][0])->not->toHaveKey('methods')
        ->and($result['classes'][0]['name'])->toBe('NoMethods');
});

it('handles classes with non-array methods', function (): void {
    $data = [
        'language' => 'ruby',
        'classes' => [
            ['name' => 'Weird', 'methods' => 'not_an_array'],
        ],
    ];

    $result = $this->truncator->truncate($data, 10000);

    expect($result['classes'][0]['methods'])->toBe('not_an_array');
});

it('does not include keys that are missing from input', function (): void {
    $data = ['language' => 'python'];

    $result = $this->truncator->truncate($data, 10000);

    expect($result)->toHaveKey('language')
        ->and($result)->not->toHaveKey('functions')
        ->and($result)->not->toHaveKey('classes')
        ->and($result)->not->toHaveKey('imports');
});

it('uses unknown as language in fallback when language is missing', function (): void {
    $data = [
        'functions' => ['fn1', 'fn2', 'fn3', 'fn4', 'fn5'],
        'classes' => [
            ['name' => 'A', 'methods' => ['m1', 'm2', 'm3', 'm4', 'm5']],
            ['name' => 'B', 'methods' => ['m1', 'm2', 'm3', 'm4', 'm5']],
            ['name' => 'C', 'methods' => ['m1', 'm2', 'm3', 'm4', 'm5']],
        ],
        'imports' => ['imp1', 'imp2', 'imp3', 'imp4', 'imp5'],
    ];

    $result = $this->truncator->truncate($data, 1);

    expect($result['language'])->toBe('unknown');
});
