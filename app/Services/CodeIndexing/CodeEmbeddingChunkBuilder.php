<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Enums\CodeIndexing\ChunkType;
use App\Models\CodeIndex;

/**
 * Builds embedding chunks from indexed source files.
 */
final class CodeEmbeddingChunkBuilder
{
    private const int MAX_CHUNK_SIZE = 8000;

    /**
     * @return array<int, array{type: ChunkType, symbol_name: string|null, content: string, metadata: array<string, mixed>}>
     */
    public function build(CodeIndex $codeIndex): array
    {
        $chunks = [];

        $fileContent = $this->truncateContent($codeIndex->content);
        if ($fileContent !== '') {
            $chunks[] = [
                'type' => ChunkType::File,
                'symbol_name' => null,
                'content' => $this->formatFileChunk($codeIndex->file_path, $fileContent),
                'metadata' => [
                    'file_path' => $codeIndex->file_path,
                    'file_type' => $codeIndex->file_type,
                ],
            ];
        }

        $structure = $codeIndex->structure;
        if (is_array($structure)) {
            return array_merge($chunks, $this->extractSymbolChunks($codeIndex, $structure));
        }

        return $chunks;
    }

    /**
     * @param  array<string, mixed>  $structure
     * @return array<int, array{type: ChunkType, symbol_name: string|null, content: string, metadata: array<string, mixed>}>
     */
    private function extractSymbolChunks(CodeIndex $codeIndex, array $structure): array
    {
        $chunks = [];

        $classes = $structure['classes'] ?? [];
        if (is_array($classes)) {
            foreach ($classes as $class) {
                if (! is_array($class)) {
                    continue;
                }

                $className = isset($class['name']) && is_string($class['name']) ? $class['name'] : null;
                $startLine = isset($class['start_line']) && is_int($class['start_line']) ? $class['start_line'] : null;
                $endLine = isset($class['end_line']) && is_int($class['end_line']) ? $class['end_line'] : null;
                $classContent = $this->extractContentByLineRange($codeIndex->content, $startLine, $endLine);

                if ($className !== null && $classContent !== '') {
                    $chunks[] = [
                        'type' => ChunkType::ClassChunk,
                        'symbol_name' => $className,
                        'content' => $this->formatSymbolChunk('class', $className, $classContent, $codeIndex->file_path),
                        'metadata' => [
                            'file_path' => $codeIndex->file_path,
                            'class' => $className,
                            'start_line' => $startLine,
                            'end_line' => $endLine,
                        ],
                    ];
                }

                $methods = $class['methods'] ?? [];
                if (is_array($methods)) {
                    foreach ($methods as $method) {
                        if (! is_array($method)) {
                            continue;
                        }

                        $methodName = isset($method['name']) && is_string($method['name']) ? $method['name'] : null;
                        $methodStartLine = isset($method['start_line']) && is_int($method['start_line']) ? $method['start_line'] : null;
                        $methodEndLine = isset($method['end_line']) && is_int($method['end_line']) ? $method['end_line'] : null;
                        $methodContent = $this->extractContentByLineRange($codeIndex->content, $methodStartLine, $methodEndLine);

                        if ($className !== null && $methodName !== null && $methodContent !== '') {
                            $fullMethodName = $className.'::'.$methodName;

                            $chunks[] = [
                                'type' => ChunkType::Method,
                                'symbol_name' => $fullMethodName,
                                'content' => $this->formatSymbolChunk('method', $fullMethodName, $methodContent, $codeIndex->file_path),
                                'metadata' => [
                                    'file_path' => $codeIndex->file_path,
                                    'class' => $className,
                                    'method' => $methodName,
                                    'start_line' => $methodStartLine,
                                    'end_line' => $methodEndLine,
                                ],
                            ];
                        }
                    }
                }
            }
        }

        $functions = $structure['functions'] ?? [];
        if (is_array($functions)) {
            foreach ($functions as $function) {
                if (! is_array($function)) {
                    continue;
                }

                $functionName = isset($function['name']) && is_string($function['name']) ? $function['name'] : null;
                $funcStartLine = isset($function['start_line']) && is_int($function['start_line']) ? $function['start_line'] : null;
                $funcEndLine = isset($function['end_line']) && is_int($function['end_line']) ? $function['end_line'] : null;
                $functionContent = $this->extractContentByLineRange($codeIndex->content, $funcStartLine, $funcEndLine);

                if ($functionName !== null && $functionContent !== '') {
                    $chunks[] = [
                        'type' => ChunkType::Function,
                        'symbol_name' => $functionName,
                        'content' => $this->formatSymbolChunk('function', $functionName, $functionContent, $codeIndex->file_path),
                        'metadata' => [
                            'file_path' => $codeIndex->file_path,
                            'function' => $functionName,
                            'start_line' => $funcStartLine,
                            'end_line' => $funcEndLine,
                        ],
                    ];
                }
            }
        }

        return $chunks;
    }

    /**
     * Extract content using one-based line boundaries.
     */
    private function extractContentByLineRange(string $content, ?int $startLine, ?int $endLine): string
    {
        if ($startLine === null || $endLine === null) {
            return '';
        }

        $lines = explode("\n", $content);
        $extracted = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

        return $this->truncateContent(implode("\n", $extracted));
    }

    /**
     * Truncate content to the configured embedding chunk limit.
     */
    private function truncateContent(string $content): string
    {
        if (mb_strlen($content) <= self::MAX_CHUNK_SIZE) {
            return $content;
        }

        return mb_substr($content, 0, self::MAX_CHUNK_SIZE)."\n... (truncated)";
    }

    /**
     * Format a file-level chunk payload.
     */
    private function formatFileChunk(string $filePath, string $content): string
    {
        return sprintf("File: %s\n\n%s", $filePath, $content);
    }

    /**
     * Format a symbol-level chunk payload.
     */
    private function formatSymbolChunk(string $type, string $name, string $content, string $filePath): string
    {
        return sprintf('%s %s in %s\n\n%s', ucfirst($type), $name, $filePath, $content);
    }
}
