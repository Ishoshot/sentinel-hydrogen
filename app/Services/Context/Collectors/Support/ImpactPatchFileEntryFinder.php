<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Locates the matching changed file entry for semantic data.
 */
final readonly class ImpactPatchFileEntryFinder
{
    /**
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}|null
     */
    public function find(array $files, string $filename): ?array
    {
        foreach ($files as $file) {
            if ($file['filename'] === $filename) {
                return $file;
            }
        }

        return null;
    }
}
