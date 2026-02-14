<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Factories;

/**
 * Builds deterministic cache keys for code search results.
 */
final readonly class CodeSearchCacheKeyFactory
{
    /**
     * Build a cache key for search results.
     *
     * @param  array<string>|null  $fileTypes
     */
    public function build(string $type, int $repositoryId, string $query, int $limit, ?array $fileTypes): string
    {
        $fileTypesHash = $fileTypes !== null ? hash('xxh128', implode(',', $fileTypes)) : 'all';

        return sprintf(
            'code_search:%s:%d:%s:%d:%s',
            $type,
            $repositoryId,
            hash('xxh128', $query),
            $limit,
            $fileTypesHash
        );
    }
}
