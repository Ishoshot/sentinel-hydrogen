<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Enums\CodeIndexing\ChunkType;
use App\Models\CodeIndex;
use App\Services\CodeIndexing\Support\CodeEmbeddingChunkContentFormatter;
use App\Services\CodeIndexing\Support\CodeEmbeddingLineRangeExtractor;
use App\Services\CodeIndexing\Support\CodeEmbeddingSymbolChunkExtractor;

/**
 * Builds embedding chunks from indexed source files.
 */
final class CodeEmbeddingChunkBuilder
{
    /**
     * Create a new chunk builder instance.
     */
    public function __construct(
        private ?CodeEmbeddingChunkContentFormatter $contentFormatter = null,
        private ?CodeEmbeddingSymbolChunkExtractor $symbolChunkExtractor = null,
    ) {}

    /**
     * @return array<int, array{type: ChunkType, symbol_name: string|null, content: string, metadata: array<string, mixed>}>
     */
    public function build(CodeIndex $codeIndex): array
    {
        $chunks = [];

        $fileContent = $this->contentFormatter()->truncate($codeIndex->content);
        if ($fileContent !== '') {
            $chunks[] = [
                'type' => ChunkType::File,
                'symbol_name' => null,
                'content' => $this->contentFormatter()->formatFileChunk($codeIndex->file_path, $fileContent),
                'metadata' => [
                    'file_path' => $codeIndex->file_path,
                    'file_type' => $codeIndex->file_type,
                ],
            ];
        }

        $structure = $codeIndex->structure;
        if (is_array($structure)) {
            return array_merge($chunks, $this->symbolChunkExtractor()->extract($codeIndex, $structure));
        }

        return $chunks;
    }

    private function contentFormatter(): CodeEmbeddingChunkContentFormatter
    {
        return $this->contentFormatter ?? new CodeEmbeddingChunkContentFormatter;
    }

    private function symbolChunkExtractor(): CodeEmbeddingSymbolChunkExtractor
    {
        return $this->symbolChunkExtractor ?? new CodeEmbeddingSymbolChunkExtractor(
            $this->contentFormatter(),
            new CodeEmbeddingLineRangeExtractor($this->contentFormatter()),
        );
    }
}
