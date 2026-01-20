<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Contracts;

use App\Models\Repository;

/**
 * Contract for code search operations.
 */
interface CodeSearchServiceContract
{
    /**
     * Search code using hybrid search (keyword + semantic).
     *
     * @param  array<string>|null  $fileTypes  Filter by file types (e.g., ['php', 'js'])
     * @return array<int, array{file_path: string, content: string, score: float, match_type: string, metadata: array<string, mixed>}>
     */
    public function search(Repository $repository, string $query, int $limit = 10, ?array $fileTypes = null): array;

    /**
     * Search code using keyword matching only.
     *
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>
     */
    public function keywordSearch(Repository $repository, string $query, int $limit = 10, ?array $fileTypes = null): array;

    /**
     * Search code using semantic similarity only.
     *
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>
     */
    public function semanticSearch(Repository $repository, string $query, int $limit = 10, ?array $fileTypes = null): array;

    /**
     * Find code by symbol name (class, method, function).
     *
     * @return array<int, array{file_path: string, symbol_name: string, chunk_type: string, content: string, metadata: array<string, mixed>}>
     */
    public function findSymbol(Repository $repository, string $symbolName, int $limit = 5): array;
}
