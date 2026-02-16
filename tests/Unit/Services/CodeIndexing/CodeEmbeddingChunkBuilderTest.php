<?php

declare(strict_types=1);

use App\Enums\CodeIndexing\ChunkType;
use App\Models\CodeIndex;
use App\Services\CodeIndexing\CodeEmbeddingChunkBuilder;

beforeEach(function (): void {
    $this->builder = new CodeEmbeddingChunkBuilder;
});

/**
 * @param  array<string, mixed>  $attributes
 */
function makeCodeIndex(array $attributes = []): CodeIndex
{
    $defaults = [
        'file_path' => 'src/Example.php',
        'file_type' => 'php',
        'content' => 'class Example {}',
        'structure' => null,
    ];

    return (new CodeIndex)->forceFill(array_merge($defaults, $attributes));
}

it('builds a file chunk from a code index without structure', function (): void {
    $codeIndex = makeCodeIndex([
        'file_path' => 'src/Example.php',
        'file_type' => 'php',
        'content' => 'class Example {}',
        'structure' => null,
    ]);

    $chunks = $this->builder->build($codeIndex);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]['type'])->toBe(ChunkType::File)
        ->and($chunks[0]['symbol_name'])->toBeNull()
        ->and($chunks[0]['content'])->toContain('File: src/Example.php')
        ->and($chunks[0]['content'])->toContain('class Example {}')
        ->and($chunks[0]['metadata'])->toBe([
            'file_path' => 'src/Example.php',
            'file_type' => 'php',
        ]);
});

it('returns empty chunks when content is empty', function (): void {
    $codeIndex = makeCodeIndex([
        'content' => '',
        'structure' => null,
    ]);

    $chunks = $this->builder->build($codeIndex);

    expect($chunks)->toBeEmpty();
});

it('builds file and class chunks when structure has classes', function (): void {
    $content = "<?php\n\nclass MyService\n{\n    public function handle(): void {}\n}";
    $codeIndex = makeCodeIndex([
        'file_path' => 'src/MyService.php',
        'file_type' => 'php',
        'content' => $content,
        'structure' => [
            'classes' => [
                [
                    'name' => 'MyService',
                    'start_line' => 3,
                    'end_line' => 6,
                    'methods' => [],
                ],
            ],
        ],
    ]);

    $chunks = $this->builder->build($codeIndex);

    expect($chunks)->toHaveCount(2);

    $fileChunk = $chunks[0];
    expect($fileChunk['type'])->toBe(ChunkType::File)
        ->and($fileChunk['symbol_name'])->toBeNull();

    $classChunk = $chunks[1];
    expect($classChunk['type'])->toBe(ChunkType::ClassChunk)
        ->and($classChunk['symbol_name'])->toBe('MyService')
        ->and($classChunk['content'])->toContain('Class MyService')
        ->and($classChunk['metadata']['file_path'])->toBe('src/MyService.php')
        ->and($classChunk['metadata']['class'])->toBe('MyService')
        ->and($classChunk['metadata']['start_line'])->toBe(3)
        ->and($classChunk['metadata']['end_line'])->toBe(6);
});

it('builds method chunks from class methods', function (): void {
    $content = "<?php\n\nclass Service\n{\n    public function execute(): void\n    {\n        // logic\n    }\n}";
    $codeIndex = makeCodeIndex([
        'file_path' => 'src/Service.php',
        'file_type' => 'php',
        'content' => $content,
        'structure' => [
            'classes' => [
                [
                    'name' => 'Service',
                    'start_line' => 3,
                    'end_line' => 9,
                    'methods' => [
                        [
                            'name' => 'execute',
                            'start_line' => 5,
                            'end_line' => 8,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $chunks = $this->builder->build($codeIndex);

    $methodChunks = array_values(array_filter($chunks, fn (array $c): bool => $c['type'] === ChunkType::Method));

    expect($methodChunks)->toHaveCount(1)
        ->and($methodChunks[0]['symbol_name'])->toBe('Service::execute')
        ->and($methodChunks[0]['content'])->toContain('Method Service::execute')
        ->and($methodChunks[0]['metadata']['class'])->toBe('Service')
        ->and($methodChunks[0]['metadata']['method'])->toBe('execute');
});

it('builds function chunks from standalone functions', function (): void {
    $content = "<?php\n\nfunction helper(): string\n{\n    return 'help';\n}";
    $codeIndex = makeCodeIndex([
        'file_path' => 'src/helpers.php',
        'file_type' => 'php',
        'content' => $content,
        'structure' => [
            'functions' => [
                [
                    'name' => 'helper',
                    'start_line' => 3,
                    'end_line' => 6,
                ],
            ],
        ],
    ]);

    $chunks = $this->builder->build($codeIndex);

    $functionChunks = array_values(array_filter($chunks, fn (array $c): bool => $c['type'] === ChunkType::Function));

    expect($functionChunks)->toHaveCount(1)
        ->and($functionChunks[0]['symbol_name'])->toBe('helper')
        ->and($functionChunks[0]['content'])->toContain('Function helper')
        ->and($functionChunks[0]['metadata']['function'])->toBe('helper');
});

it('builds chunks for classes methods and functions together', function (): void {
    $content = "<?php\n\nfunction util(): void {}\n\nclass Handler\n{\n    public function run(): void {}\n}";
    $codeIndex = makeCodeIndex([
        'file_path' => 'src/Handler.php',
        'file_type' => 'php',
        'content' => $content,
        'structure' => [
            'classes' => [
                [
                    'name' => 'Handler',
                    'start_line' => 5,
                    'end_line' => 8,
                    'methods' => [
                        [
                            'name' => 'run',
                            'start_line' => 7,
                            'end_line' => 7,
                        ],
                    ],
                ],
            ],
            'functions' => [
                [
                    'name' => 'util',
                    'start_line' => 3,
                    'end_line' => 3,
                ],
            ],
        ],
    ]);

    $chunks = $this->builder->build($codeIndex);

    $types = array_map(fn (array $c): ChunkType => $c['type'], $chunks);

    expect($types)->toContain(ChunkType::File)
        ->toContain(ChunkType::ClassChunk)
        ->toContain(ChunkType::Method)
        ->toContain(ChunkType::Function);
});

it('skips class entries that are not arrays', function (): void {
    $codeIndex = makeCodeIndex([
        'content' => 'some content',
        'structure' => [
            'classes' => ['not_an_array'],
        ],
    ]);

    $chunks = $this->builder->build($codeIndex);

    $classChunks = array_filter($chunks, fn (array $c): bool => $c['type'] === ChunkType::ClassChunk);

    expect($classChunks)->toBeEmpty();
});

it('skips classes without a name', function (): void {
    $codeIndex = makeCodeIndex([
        'content' => "line1\nline2\nline3",
        'structure' => [
            'classes' => [
                [
                    'start_line' => 1,
                    'end_line' => 3,
                    'methods' => [],
                ],
            ],
        ],
    ]);

    $chunks = $this->builder->build($codeIndex);

    $classChunks = array_filter($chunks, fn (array $c): bool => $c['type'] === ChunkType::ClassChunk);

    expect($classChunks)->toBeEmpty();
});

it('truncates large file content in file chunks', function (): void {
    $largeContent = str_repeat("x\n", 5000);
    $codeIndex = makeCodeIndex([
        'file_path' => 'src/Large.php',
        'file_type' => 'php',
        'content' => $largeContent,
        'structure' => null,
    ]);

    $chunks = $this->builder->build($codeIndex);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]['type'])->toBe(ChunkType::File);
});
