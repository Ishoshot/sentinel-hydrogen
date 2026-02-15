<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Parsers;

/**
 * Extracts and truncates one-based line ranges from source content.
 */
final readonly class CodeEmbeddingLineRangeParser
{
    /**
     * Create a new parser instance.
     */
    public function __construct(
        private CodeEmbeddingChunkContentParser $contentParser = new CodeEmbeddingChunkContentParser,
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

        return $this->contentParser->truncate(implode("\n", $extracted));
    }
}
