<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Parsers;

/**
 * Truncates and formats code embedding chunk content payloads.
 */
final readonly class CodeEmbeddingChunkContentParser
{
    private const int MAX_CHUNK_SIZE = 8000;

    /**
     * Truncate content to the configured embedding chunk limit.
     */
    public function truncate(string $content): string
    {
        if (mb_strlen($content) <= self::MAX_CHUNK_SIZE) {
            return $content;
        }

        return mb_substr($content, 0, self::MAX_CHUNK_SIZE)."\n... (truncated)";
    }

    /**
     * Format a file-level chunk payload.
     */
    public function formatFileChunk(string $filePath, string $content): string
    {
        return sprintf("File: %s\n\n%s", $filePath, $content);
    }

    /**
     * Format a symbol-level chunk payload.
     */
    public function formatSymbolChunk(string $type, string $name, string $content, string $filePath): string
    {
        return sprintf('%s %s in %s\n\n%s', ucfirst($type), $name, $filePath, $content);
    }
}
