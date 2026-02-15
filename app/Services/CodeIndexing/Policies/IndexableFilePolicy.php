<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Policies;

/**
 * Determines whether a file should be indexed based on its extension and path.
 */
final readonly class IndexableFilePolicy
{
    /** @var array<string> */
    private const array INDEXABLE_EXTENSIONS = [
        // Core languages
        'php', 'js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx', 'py', 'go', 'rs',
        // JVM languages
        'java', 'kt', 'kts', 'scala', 'groovy',
        // .NET
        'cs', 'fs',
        // Dynamic languages
        'rb',
        // Apple ecosystem
        'swift',
        // Systems languages
        'c', 'h', 'cpp', 'cc', 'cxx', 'hpp',
        // Frontend frameworks
        'vue', 'svelte',
        // Functional languages
        'ex', 'exs', 'hs',
        // Data & config (for understanding structure)
        'sql', 'yaml', 'yml', 'json',
        // Shell
        'sh', 'bash',
        // Markup (for documentation)
        'md', 'mdx',
    ];

    /** @var array<string> */
    private const array EXCLUDED_PATHS = [
        'vendor/',
        'node_modules/',
        '.git/',
        'dist/',
        'build/',
        'storage/',
        'public/build/',
        'public/vendor/',
        '.idea/',
        '.vscode/',
        '__pycache__/',
        '.pytest_cache/',
        'coverage/',
        '.nyc_output/',
    ];

    private const int MAX_FILE_SIZE = 512_000; // 500KB

    /**
     * Check if a file should be indexed based on type and path.
     */
    public function shouldIndex(string $filePath): bool
    {
        foreach (self::EXCLUDED_PATHS as $excluded) {
            if (str_starts_with($filePath, $excluded) || str_contains($filePath, '/'.$excluded)) {
                return false;
            }
        }

        $extension = mb_strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, self::INDEXABLE_EXTENSIONS, true);
    }

    /**
     * Filter tree items to only include indexable files.
     *
     * @param  array<int, array{path: string, type: string, size?: int}>  $tree
     * @return array<int, array{path: string, type: string, size?: int}>
     */
    public function filterTree(array $tree): array
    {
        return array_filter($tree, function (array $item): bool {
            if ($item['type'] !== 'blob') {
                return false;
            }

            if (isset($item['size']) && $item['size'] > self::MAX_FILE_SIZE) {
                return false;
            }

            return $this->shouldIndex($item['path']);
        });
    }
}
