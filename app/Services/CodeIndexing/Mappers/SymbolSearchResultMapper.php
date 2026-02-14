<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Support;

use App\Models\CodeEmbedding;

final readonly class SymbolSearchResultMapper
{
    /**
     * @return array{file_path: string, symbol_name: string, chunk_type: \App\Enums\CodeIndexing\ChunkType|string, content: string, metadata: array<string, mixed>}
     */
    public function map(CodeEmbedding $embedding): array
    {
        return [
            'file_path' => $embedding->codeIndex?->file_path ?? '',
            'symbol_name' => $embedding->symbol_name ?? '',
            'chunk_type' => $embedding->chunk_type,
            'content' => $embedding->content,
            'metadata' => [
                'file_type' => $embedding->codeIndex?->file_type,
                ...(is_array($embedding->metadata) ? $embedding->metadata : []),
            ],
        ];
    }
}
