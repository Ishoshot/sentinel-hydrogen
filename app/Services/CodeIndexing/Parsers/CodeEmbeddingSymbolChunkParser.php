<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Parsers;

use App\Enums\CodeIndexing\ChunkType;
use App\Models\CodeIndex;

/**
 * Extracts class, method, and function chunks from semantic structure.
 */
final readonly class CodeEmbeddingSymbolChunkParser
{
    /**
     * Create a new parser instance.
     */
    public function __construct(
        private CodeEmbeddingChunkContentParser $contentParser = new CodeEmbeddingChunkContentParser,
        private CodeEmbeddingLineRangeParser $lineRangeParser = new CodeEmbeddingLineRangeParser,
    ) {}

    /**
     * @param  array<string, mixed>  $structure
     * @return array<int, array{type: ChunkType, symbol_name: string|null, content: string, metadata: array<string, mixed>}>
     */
    public function extract(CodeIndex $codeIndex, array $structure): array
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
                $classContent = $this->lineRangeParser->extract($codeIndex->content, $startLine, $endLine);

                if ($className !== null && $classContent !== '') {
                    $chunks[] = [
                        'type' => ChunkType::ClassChunk,
                        'symbol_name' => $className,
                        'content' => $this->contentParser->formatSymbolChunk('class', $className, $classContent, $codeIndex->file_path),
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
                        $methodContent = $this->lineRangeParser->extract($codeIndex->content, $methodStartLine, $methodEndLine);

                        if ($className !== null && $methodName !== null && $methodContent !== '') {
                            $fullMethodName = $className.'::'.$methodName;

                            $chunks[] = [
                                'type' => ChunkType::Method,
                                'symbol_name' => $fullMethodName,
                                'content' => $this->contentParser->formatSymbolChunk('method', $fullMethodName, $methodContent, $codeIndex->file_path),
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
                $functionContent = $this->lineRangeParser->extract($codeIndex->content, $funcStartLine, $funcEndLine);

                if ($functionName !== null && $functionContent !== '') {
                    $chunks[] = [
                        'type' => ChunkType::Function,
                        'symbol_name' => $functionName,
                        'content' => $this->contentParser->formatSymbolChunk('function', $functionName, $functionContent, $codeIndex->file_path),
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
}
