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

        if ($pathsConfig->ignore !== []) {
            $bag->files = array_values(array_filter(
                $bag->files,
                fn (array $file): bool => ! $this->matchesAnyPattern($file['filename'], $pathsConfig->ignore)
            ));
        }

        if ($pathsConfig->include !== []) {
            $bag->files = array_values(array_filter(
                $bag->files,
                fn (array $file): bool => $this->matchesAnyPattern($file['filename'], $pathsConfig->include)
            ));
        }

        $sensitiveCount = $this->markSensitiveFiles($bag, $pathsConfig);
        $bag->recalculateMetrics();

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
     * Mark files matching sensitive patterns.
     */
    private function markSensitiveFiles(ContextBag $bag, PathsConfig $pathsConfig): int
    {
        if ($pathsConfig->sensitive === []) {
            return 0;
        }

        $sensitiveFiles = [];
        $bag->files = array_map(function (array $file) use ($pathsConfig, &$sensitiveFiles): array {
            if ($this->matchesAnyPattern($file['filename'], $pathsConfig->sensitive)) {
                $file['is_sensitive'] = true;
                $sensitiveFiles[] = $file['filename'];
            }

            return $file;
        }, $bag->files);

        if ($sensitiveFiles !== []) {
            $bag->metadata['sensitive_files'] = $sensitiveFiles;
        }

        return count($sensitiveFiles);
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
        return array_any($patterns, fn (string $pattern): bool => $this->matchesGlob($path, $pattern));
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
