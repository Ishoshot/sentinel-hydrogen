<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Enums\CodeIndexing\ChunkType;
use App\Models\CodeIndex;
use App\Services\CodeIndexing\Parsers\CodeEmbeddingChunkContentParser;
use App\Services\CodeIndexing\Parsers\CodeEmbeddingLineRangeParser;
use App\Services\CodeIndexing\Parsers\CodeEmbeddingSymbolChunkParser;

/**
 * Builds embedding chunks from indexed source files.
 */
final readonly class CodeEmbeddingChunkBuilder
{
    /**
     * Create a new chunk builder instance.
     */
    public function __construct(
        private ?CodeEmbeddingChunkContentParser $contentParser = null,
        private ?CodeEmbeddingSymbolChunkParser $symbolChunkParser = null,
    ) {}

    /**
     * @return array<int, array{type: ChunkType, symbol_name: string|null, content: string, metadata: array<string, mixed>}>
     */
    public function build(CodeIndex $codeIndex): array
    {
        $chunks = [];

        $fileContent = $this->contentParser()->truncate($codeIndex->content);
        if ($fileContent !== '') {
            $chunks[] = [
                'type' => ChunkType::File,
                'symbol_name' => null,
                'content' => $this->contentParser()->formatFileChunk($codeIndex->file_path, $fileContent),
                'metadata' => [
                    'file_path' => $codeIndex->file_path,
                    'file_type' => $codeIndex->file_type,
                ],
            ];
        }

        $structure = $codeIndex->structure;
        if (is_array($structure)) {
            return array_merge($chunks, $this->symbolChunkParser()->extract($codeIndex, $structure));
        }

        return $chunks;
    }

    /**
     * ContentParser.
     */
    private function contentParser(): CodeEmbeddingChunkContentParser
    {
        return $this->contentParser ?? new CodeEmbeddingChunkContentParser;
    }

    /**
     * SymbolChunkParser.
     */
    private function symbolChunkParser(): CodeEmbeddingSymbolChunkParser
    {
        return $this->symbolChunkParser ?? new CodeEmbeddingSymbolChunkParser(
            $this->contentParser(),
            new CodeEmbeddingLineRangeParser($this->contentParser()),
        );
    }
}
