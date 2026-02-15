<?php

declare(strict_types=1);

use App\Services\Context\Filters\Support\SemanticEntrySanitizer;
use App\Services\Context\SensitiveDataRedactor;

beforeEach(function (): void {
    $this->sanitizer = new SemanticEntrySanitizer(new SensitiveDataRedactor);
});

it('returns empty array for empty semantics', function (): void {
    $redactedCount = 0;

    $result = $this->sanitizer->sanitize([], $redactedCount);

    expect($result)->toBe([])
        ->and($redactedCount)->toBe(0);
});

it('does not redact clean string values', function (): void {
    $redactedCount = 0;
    $semantics = [
        'file.php' => [
            'language' => 'php',
            'class_name' => 'UserController',
        ],
    ];

    $result = $this->sanitizer->sanitize($semantics, $redactedCount);

    expect($result['file.php']['language'])->toBe('php')
        ->and($result['file.php']['class_name'])->toBe('UserController')
        ->and($redactedCount)->toBe(0);
});

it('redacts api keys in string values', function (): void {
    $redactedCount = 0;
    $semantics = [
        'config.php' => [
            'content' => 'api_key=sk-1234567890abcdefghij',
        ],
    ];

    $result = $this->sanitizer->sanitize($semantics, $redactedCount);

    expect($result['config.php']['content'])->toContain('[REDACTED:api_key:')
        ->and($redactedCount)->toBe(1);
});

it('redacts sensitive data in nested arrays', function (): void {
    $redactedCount = 0;
    $semantics = [
        'app.php' => [
            'functions' => [
                'getToken' => 'api_key=sk-abcdefghijklmnopqrst',
                'normal' => 'just a regular function',
            ],
        ],
    ];

    $result = $this->sanitizer->sanitize($semantics, $redactedCount);

    expect($result['app.php']['functions']['getToken'])->toContain('[REDACTED:api_key:')
        ->and($result['app.php']['functions']['normal'])->toBe('just a regular function')
        ->and($redactedCount)->toBe(1);
});

it('redacts multiple sensitive values and counts each', function (): void {
    $redactedCount = 0;
    $semantics = [
        'env.php' => [
            'key1' => 'api_key=sk-1234567890abcdefghij',
            'key2' => 'password=supersecretpassword123',
        ],
    ];

    $result = $this->sanitizer->sanitize($semantics, $redactedCount);

    expect($redactedCount)->toBe(2)
        ->and($result['env.php']['key1'])->toContain('[REDACTED:')
        ->and($result['env.php']['key2'])->toContain('[REDACTED:');
});

it('sanitizes deeply nested arrays recursively', function (): void {
    $redactedCount = 0;
    $semantics = [
        'deep.php' => [
            'level1' => [
                ['level2' => [
                    'secret' => 'api_key=sk-1234567890abcdefghij',
                ]],
            ],
        ],
    ];

    $result = $this->sanitizer->sanitize($semantics, $redactedCount);

    expect($result['deep.php']['level1'][0]['level2']['secret'])->toContain('[REDACTED:api_key:')
        ->and($redactedCount)->toBe(1);
});

it('preserves non-string non-array values in entries', function (): void {
    $redactedCount = 0;
    $semantics = [
        'file.php' => [
            'line_count' => 42,
            'is_abstract' => true,
            'nullable' => null,
        ],
    ];

    $result = $this->sanitizer->sanitize($semantics, $redactedCount);

    expect($result['file.php']['line_count'])->toBe(42)
        ->and($result['file.php']['is_abstract'])->toBeTrue()
        ->and($result['file.php']['nullable'])->toBeNull()
        ->and($redactedCount)->toBe(0);
});

it('preserves non-string non-array values in nested arrays', function (): void {
    $redactedCount = 0;
    $semantics = [
        'file.php' => [
            'methods' => [
                'count' => 5,
                'active' => false,
            ],
        ],
    ];

    $result = $this->sanitizer->sanitize($semantics, $redactedCount);

    expect($result['file.php']['methods']['count'])->toBe(5)
        ->and($result['file.php']['methods']['active'])->toBeFalse()
        ->and($redactedCount)->toBe(0);
});

it('sanitizes multiple entry keys independently', function (): void {
    $redactedCount = 0;
    $semantics = [
        'a.php' => [
            'content' => 'api_key=sk-1234567890abcdefghij',
        ],
        'b.php' => [
            'content' => 'clean string',
        ],
        'c.php' => [
            'content' => 'password=mysecretpassword123',
        ],
    ];

    $result = $this->sanitizer->sanitize($semantics, $redactedCount);

    expect($redactedCount)->toBe(2)
        ->and($result['a.php']['content'])->toContain('[REDACTED:')
        ->and($result['b.php']['content'])->toBe('clean string')
        ->and($result['c.php']['content'])->toContain('[REDACTED:');
});

it('accumulates redaction count across calls', function (): void {
    $redactedCount = 5; // Start with existing count
    $semantics = [
        'file.php' => [
            'content' => 'api_key=sk-1234567890abcdefghij',
        ],
    ];

    $this->sanitizer->sanitize($semantics, $redactedCount);

    expect($redactedCount)->toBe(6);
});
