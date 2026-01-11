<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use Illuminate\Support\Facades\Log;

/**
 * Applies repository-specific path filtering based on sentinel config.
 *
 * - Removes files matching ignore patterns
 * - In allowlist mode (include set), keeps only files matching include patterns
 * - Marks files matching sensitive patterns for extra scrutiny
 */
final class ConfiguredPathFilter implements ContextFilter
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'configured_path';
    }

    /**
     * {@inheritdoc}
     */
    public function order(): int
    {
        return 15; // Run after VendorPathFilter (10), before BinaryFileFilter (20)
    }

    /**
     * {@inheritdoc}
     */
    public function filter(ContextBag $bag): void
    {
        $pathsConfig = $this->getPathsConfig($bag);

        if (! $pathsConfig instanceof PathsConfig) {
            return;
        }

        $originalCount = count($bag->files);
        $sensitiveCount = 0;

        // Apply ignore patterns
        if ($pathsConfig->ignore !== []) {
            $bag->files = array_values(array_filter(
                $bag->files,
                fn (array $file): bool => ! $this->matchesAnyPattern($file['filename'], $pathsConfig->ignore)
            ));
        }

        // Apply include patterns (allowlist mode)
        if ($pathsConfig->include !== []) {
            $bag->files = array_values(array_filter(
                $bag->files,
                fn (array $file): bool => $this->matchesAnyPattern($file['filename'], $pathsConfig->include)
            ));
        }

        // Mark sensitive files
        if ($pathsConfig->sensitive !== []) {
            $sensitiveFiles = [];
            $bag->files = array_map(function (array $file) use ($pathsConfig, &$sensitiveFiles): array {
                $isSensitive = $this->matchesAnyPattern($file['filename'], $pathsConfig->sensitive);
                if ($isSensitive) {
                    $file['is_sensitive'] = true;
                    $sensitiveFiles[] = $file['filename'];
                }

                return $file;
            }, $bag->files);

            $sensitiveCount = count($sensitiveFiles);

            if ($sensitiveCount > 0) {
                $bag->metadata['sensitive_files'] = $sensitiveFiles;
            }
        }

        // Recalculate metrics after filtering
        $bag->metrics = [
            'files_changed' => count($bag->files),
            'lines_added' => array_sum(array_column($bag->files, 'additions')),
            'lines_deleted' => array_sum(array_column($bag->files, 'deletions')),
        ];

        $removedCount = $originalCount - count($bag->files);
        if ($removedCount > 0 || $sensitiveCount > 0) {
            Log::debug('ConfiguredPathFilter: Applied path rules', [
                'original_files' => $originalCount,
                'removed_files' => $removedCount,
                'sensitive_files' => $sensitiveCount,
                'remaining_files' => count($bag->files),
            ]);
        }
    }

    /**
     * Get PathsConfig from the bag's metadata.
     */
    private function getPathsConfig(ContextBag $bag): ?PathsConfig
    {
        $pathsData = $bag->metadata['paths_config'] ?? null;

        if (! is_array($pathsData)) {
            return null;
        }

        /** @var array<string, mixed> $pathsData */
        return PathsConfig::fromArray($pathsData);
    }

    /**
     * Check if a path matches any of the given glob patterns.
     *
     * @param  array<int, string>  $patterns
     */
    private function matchesAnyPattern(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matchesGlob($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a path matches a glob pattern.
     *
     * Supports:
     * - * matches any sequence of characters except /
     * - ** matches any sequence including /
     * - ? matches any single character except /
     */
    private function matchesGlob(string $path, string $pattern): bool
    {
        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Convert glob pattern to regex
        $regex = $this->globToRegex($pattern);

        return preg_match($regex, $path) === 1;
    }

    /**
     * Convert a glob pattern to a regex pattern.
     */
    private function globToRegex(string $pattern): string
    {
        // Escape special regex characters except * and ?
        $escaped = preg_quote($pattern, '/');

        // Handle ** (match anything including directory separators)
        $escaped = str_replace('\*\*', '.*', $escaped);

        // Handle * (match anything except directory separators)
        $escaped = str_replace('\*', '[^\/]*', $escaped);

        // Handle ? (match single character except directory separator)
        $escaped = str_replace('\?', '[^\/]', $escaped);

        return '/^'.$escaped.'$/';
    }
}
