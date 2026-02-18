<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Selects which PR files to fetch full content for based on extension and change volume.
 */
final readonly class FileSelectionPolicy
{
    /**
     * File extensions to fetch (code files only).
     */
    private const array ALLOWED_EXTENSIONS = [
        'php', 'js', 'ts', 'jsx', 'tsx', 'vue', 'svelte',
        'py', 'rb', 'go', 'rs', 'java', 'kt', 'scala',
        'cs', 'cpp', 'c', 'h', 'hpp',
        'swift', 'dart', 'ex', 'exs',
        'yaml', 'yml', 'json', 'xml', 'toml',
        'sql', 'graphql', 'gql',
        'sh', 'bash', 'zsh',
        'md', 'mdx', 'txt',
    ];

    /**
     * Select which files to fetch full content for.
     *
     * Prioritizes modified files with code changes, skips deleted files
     * and files with unsupported extensions.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    public function select(array $files, int $maxFiles): array
    {
        $candidates = [];

        foreach ($files as $file) {
            if ($file['status'] === 'removed') {
                continue;
            }

            $extension = mb_strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $candidates[] = $file;
        }

        usort($candidates, static fn (array $a, array $b): int => $b['changes'] <=> $a['changes']);

        $resolvedMaxFiles = max(1, $maxFiles);

        return array_slice($candidates, 0, $resolvedMaxFiles);
    }
}
