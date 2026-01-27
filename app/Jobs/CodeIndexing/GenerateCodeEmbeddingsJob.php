<?php

declare(strict_types=1);

namespace App\Jobs\CodeIndexing;

use App\Enums\ChunkType;
use App\Enums\Queue;
use App\Models\CodeEmbedding;
use App\Models\CodeIndex;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\EmbeddingServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates vector embeddings for indexed code files.
 */
final class GenerateCodeEmbeddingsJob implements ShouldQueue
{
    use Queueable;

    private const int MAX_CHUNK_SIZE = 8000;

    /**
     * @param  array<int>  $codeIndexIds
     */
    public function __construct(
        public Repository $repository,
        public array $codeIndexIds,
    ) {
        $this->onQueue(Queue::CodeIndexing->value);
    }

    /**
     * Execute the job.
     */
    public function handle(EmbeddingServiceContract $embeddingService): void
    {
        Log::info('Generating embeddings for indexed code', [
            'repository_id' => $this->repository->id,
            'code_index_count' => count($this->codeIndexIds),
        ]);

        $totalEmbeddings = 0;

        // Process in chunks to avoid memory issues with large batches
        CodeIndex::whereIn('id', $this->codeIndexIds)
            ->lazyById(100, 'id')
            ->each(function (CodeIndex $codeIndex) use ($embeddingService, &$totalEmbeddings): void {
                try {
                    $embeddingsCreated = $this->processCodeIndex($codeIndex, $embeddingService);
                    $totalEmbeddings += $embeddingsCreated;
                } catch (Throwable $e) {
                    Log::warning('Failed to generate embeddings for file', [
                        'code_index_id' => $codeIndex->id,
                        'file_path' => $codeIndex->file_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        Log::info('Completed embedding generation', [
            'repository_id' => $this->repository->id,
            'files_processed' => count($this->codeIndexIds),
            'embeddings_created' => $totalEmbeddings,
        ]);
    }

    /**
     * Process a single code index and generate embeddings.
     */
    private function processCodeIndex(CodeIndex $codeIndex, EmbeddingServiceContract $embeddingService): int
    {
        $chunks = $this->extractChunks($codeIndex);

        if ($chunks === []) {
            return 0;
        }

        // Generate embeddings in batch
        $contents = array_column($chunks, 'content');
        $embeddings = $embeddingService->generateEmbeddings($contents);

        if (count($embeddings) !== count($chunks)) {
            Log::warning('Embedding count mismatch', [
                'code_index_id' => $codeIndex->id,
                'chunks' => count($chunks),
                'embeddings' => count($embeddings),
            ]);

            return 0;
        }

        // Wrap delete and insert in a transaction to maintain data consistency
        return DB::transaction(function () use ($codeIndex, $chunks, $embeddings): int {
            // Delete existing embeddings for this file
            CodeEmbedding::where('code_index_id', $codeIndex->id)->delete();

            // Insert embeddings
            $insertData = [];
            $now = now();

            foreach ($chunks as $chunk) {
                $insertData[] = [
                    'code_index_id' => $codeIndex->id,
                    'repository_id' => $codeIndex->repository_id,
                    'chunk_type' => $chunk['type']->value,
                    'symbol_name' => $chunk['symbol_name'],
                    'content' => $chunk['content'],
                    'metadata' => json_encode($chunk['metadata']),
                    'created_at' => $now,
                ];
            }

            // Batch insert without embeddings first
            CodeEmbedding::insert($insertData);

            // Update with embeddings using batch SQL for pgvector
            $insertedEmbeddings = CodeEmbedding::where('code_index_id', $codeIndex->id)
                ->orderBy('id')
                ->get();

            // Build a single batch update query using CASE statement
            $cases = [];
            $ids = [];

            foreach ($insertedEmbeddings as $index => $embedding) {
                if (! isset($embeddings[$index])) {
                    continue;
                }

                // Validate embedding is an array before using implode
                if (! is_array($embeddings[$index])) {
                    Log::warning('Invalid embedding format, skipping', [
                        'code_index_id' => $codeIndex->id,
                        'index' => $index,
                    ]);

                    continue;
                }

                $vectorString = '['.implode(',', $embeddings[$index]).']';
                $cases[] = "WHEN id = {$embedding->id} THEN '{$vectorString}'::vector";
                $ids[] = $embedding->id;
            }

            if ($cases !== []) {
                $caseStatement = implode(' ', $cases);
                $idList = implode(',', $ids);
                DB::statement(
                    "UPDATE code_embeddings SET embedding = CASE {$caseStatement} END WHERE id IN ({$idList})"
                );
            }

            return count($insertData);
        });
    }

    /**
     * Extract chunks from a code index for embedding.
     *
     * @return array<int, array{type: ChunkType, symbol_name: string|null, content: string, metadata: array<string, mixed>}>
     */
    private function extractChunks(CodeIndex $codeIndex): array
    {
        $chunks = [];

        // Always create a file-level chunk
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

        // Extract symbol-level chunks from structure
        $structure = $codeIndex->structure;
        if (is_array($structure)) {
            $symbolChunks = $this->extractSymbolChunks($codeIndex, $structure);
            $chunks = array_merge($chunks, $symbolChunks);
        }

        return $chunks;
    }

    /**
     * Extract chunks for individual symbols (classes, methods, functions).
     *
     * @param  array<string, mixed>  $structure
     * @return array<int, array{type: ChunkType, symbol_name: string|null, content: string, metadata: array<string, mixed>}>
     */
    private function extractSymbolChunks(CodeIndex $codeIndex, array $structure): array
    {
        $chunks = [];

        // Extract classes
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

                // Extract methods from the class
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

        // Extract standalone functions
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
     * Extract content by line range.
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
     * Truncate content to fit within embedding limits.
     */
    private function truncateContent(string $content): string
    {
        if (mb_strlen($content) <= self::MAX_CHUNK_SIZE) {
            return $content;
        }

        return mb_substr($content, 0, self::MAX_CHUNK_SIZE)."\n... (truncated)";
    }

    /**
     * Format a file chunk for embedding.
     */
    private function formatFileChunk(string $filePath, string $content): string
    {
        return sprintf("File: %s\n\n%s", $filePath, $content);
    }

    /**
     * Format a symbol chunk for embedding.
     */
    private function formatSymbolChunk(string $type, string $name, string $content, string $filePath): string
    {
        return sprintf("%s %s in %s\n\n%s", ucfirst($type), $name, $filePath, $content);
    }
}
