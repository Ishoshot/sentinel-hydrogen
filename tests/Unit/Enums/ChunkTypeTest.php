<?php

declare(strict_types=1);

use App\Enums\CodeIndexing\ChunkType;

it('returns all values', function (): void {
    $values = ChunkType::values();

    expect($values)->toBeArray()
        ->toContain('file')
        ->toContain('class')
        ->toContain('method')
        ->toContain('function');
});

it('has correct cases', function (): void {
    expect(ChunkType::cases())->toHaveCount(4);
    expect(ChunkType::File->value)->toBe('file');
    expect(ChunkType::ClassChunk->value)->toBe('class');
    expect(ChunkType::Method->value)->toBe('method');
    expect(ChunkType::Function->value)->toBe('function');
});
