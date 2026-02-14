<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Support;

/**
 * Extracts and truncates one-based line ranges from source content.
 */
final readonly class CodeEmbeddingLineRangeExtractor
{
    /**
     * Create a new extractor instance.
     */
    public function __construct(
        private CodeEmbeddingChunkContentFormatter $contentFormatter = new CodeEmbeddingChunkContentFormatter,
    ) {}

    /**
     * Extract content using one-based line boundaries.
     */
    public function extract(string $content, ?int $startLine, ?int $endLine): string
    {
        if ($startLine === null || $endLine === null) {
            return '';
        }

        $lines = explode("\n", $content);
        $extracted = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

        return $this->contentFormatter->truncate(implode("\n", $extracted));
    }
}
