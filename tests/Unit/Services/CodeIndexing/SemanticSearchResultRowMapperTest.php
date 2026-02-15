<?php

declare(strict_types=1);

use App\Services\CodeIndexing\Mappers\SemanticSearchResultRowMapper;

beforeEach(function (): void {
    $this->mapper = new SemanticSearchResultRowMapper;
});

it('maps a database row to a result array', function (): void {
    $row = (object) [
        'file_path' => 'src/Example.php',
        'content' => 'class Example {}',
        'distance' => 0.2,
        'file_type' => 'php',
        'chunk_type' => 'file',
        'symbol_name' => null,
        'metadata' => null,
    ];

    $result = $this->mapper->map($row);

    expect($result)->toBe([
        'file_path' => 'src/Example.php',
        'content' => 'class Example {}',
        'score' => 0.8,
        'metadata' => [
            'file_type' => 'php',
            'chunk_type' => 'file',
            'symbol_name' => null,
            'match_type' => 'semantic',
        ],
    ]);
});

it('calculates score as 1 minus distance', function (): void {
    $row = (object) [
        'file_path' => 'src/App.php',
        'content' => 'content',
        'distance' => 0.35,
        'file_type' => 'php',
        'chunk_type' => 'file',
        'symbol_name' => null,
        'metadata' => null,
    ];

    $result = $this->mapper->map($row);

    expect($result['score'])->toBe(0.65);
});

it('defaults distance to 0.5 when not numeric', function (): void {
    $row = (object) [
        'file_path' => 'src/App.php',
        'content' => 'content',
        'distance' => 'invalid',
        'file_type' => 'php',
        'chunk_type' => 'file',
        'symbol_name' => null,
        'metadata' => null,
    ];

    $result = $this->mapper->map($row);

    expect($result['score'])->toBe(0.5);
});

it('includes symbol name in metadata', function (): void {
    $row = (object) [
        'file_path' => 'src/Service.php',
        'content' => 'public function handle() {}',
        'distance' => 0.1,
        'file_type' => 'php',
        'chunk_type' => 'method',
        'symbol_name' => 'Service::handle',
        'metadata' => null,
    ];

    $result = $this->mapper->map($row);

    expect($result['metadata']['symbol_name'])->toBe('Service::handle')
        ->and($result['metadata']['chunk_type'])->toBe('method')
        ->and($result['metadata']['match_type'])->toBe('semantic');
});

it('parses json string metadata', function (): void {
    $row = (object) [
        'file_path' => 'src/App.php',
        'content' => 'content',
        'distance' => 0.2,
        'file_type' => 'php',
        'chunk_type' => 'file',
        'symbol_name' => null,
        'metadata' => json_encode(['start_line' => 1, 'end_line' => 50]),
    ];

    $result = $this->mapper->map($row);

    expect($result['metadata']['start_line'])->toBe(1)
        ->and($result['metadata']['end_line'])->toBe(50);
});

it('handles array metadata directly', function (): void {
    $row = (object) [
        'file_path' => 'src/App.php',
        'content' => 'content',
        'distance' => 0.2,
        'file_type' => 'php',
        'chunk_type' => 'file',
        'symbol_name' => null,
        'metadata' => ['class' => 'App', 'method' => 'boot'],
    ];

    $result = $this->mapper->map($row);

    expect($result['metadata']['class'])->toBe('App')
        ->and($result['metadata']['method'])->toBe('boot');
});

it('ignores non-string keys in metadata', function (): void {
    $row = (object) [
        'file_path' => 'src/App.php',
        'content' => 'content',
        'distance' => 0.2,
        'file_type' => 'php',
        'chunk_type' => 'file',
        'symbol_name' => null,
        'metadata' => [0 => 'indexed_value', 'valid_key' => 'valid_value'],
    ];

    $result = $this->mapper->map($row);

    expect($result['metadata'])->not->toHaveKey(0)
        ->and($result['metadata']['valid_key'])->toBe('valid_value');
});

it('handles null metadata gracefully', function (): void {
    $row = (object) [
        'file_path' => 'src/App.php',
        'content' => 'content',
        'distance' => 0.2,
        'file_type' => 'php',
        'chunk_type' => 'file',
        'symbol_name' => null,
        'metadata' => null,
    ];

    $result = $this->mapper->map($row);

    expect($result['metadata'])->toBe([
        'file_type' => 'php',
        'chunk_type' => 'file',
        'symbol_name' => null,
        'match_type' => 'semantic',
    ]);
});

it('always includes match_type as semantic', function (): void {
    $row = (object) [
        'file_path' => 'src/App.php',
        'content' => 'content',
        'distance' => 0.0,
        'file_type' => 'php',
        'chunk_type' => 'class',
        'symbol_name' => 'App',
        'metadata' => null,
    ];

    $result = $this->mapper->map($row);

    expect($result['metadata']['match_type'])->toBe('semantic');
});

it('casts file_path and content to strings', function (): void {
    $row = (object) [
        'file_path' => 123,
        'content' => 456,
        'distance' => 0.2,
        'file_type' => 'php',
        'chunk_type' => 'file',
        'symbol_name' => null,
        'metadata' => null,
    ];

    $result = $this->mapper->map($row);

    expect($result['file_path'])->toBe('123')
        ->and($result['content'])->toBe('456');
});
