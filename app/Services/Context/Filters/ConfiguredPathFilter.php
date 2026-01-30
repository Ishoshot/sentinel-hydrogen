<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use App\Support\PathRuleMatcher;
use Illuminate\Support\Facades\Log;

/**
 * Applies repository-specific path filtering based on sentinel config.
 *
 * - Removes files matching ignore patterns
 * - In allowlist mode (include set), keeps only files matching include patterns
 * - Marks files matching sensitive patterns for extra scrutiny
 */
final readonly class ConfiguredPathFilter implements ContextFilter
{
    /**
     * Create a new ConfiguredPathFilter instance.
     */
    public function __construct(private PathRuleMatcher $matcher) {}

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
                fn (array $file): bool => ! $this->matcher->matchesAny($file['filename'], $pathsConfig->ignore)
            ));
        }

        if ($pathsConfig->include !== []) {
            $bag->files = array_values(array_filter(
                $bag->files,
                fn (array $file): bool => $this->matcher->matchesAny($file['filename'], $pathsConfig->include)
            ));
        }

        $sensitiveCount = $this->markSensitiveFiles($bag, $pathsConfig);
        $removedFileContents = $this->filterFileContents($bag, $pathsConfig);
        $removedSemantics = $this->filterSemantics($bag, $pathsConfig);
        $removedGuidelines = $this->filterGuidelines($bag, $pathsConfig);
        $removedRepositoryContext = $this->filterRepositoryContext($bag, $pathsConfig);
        $bag->recalculateMetrics();

        $removedCount = $originalCount - count($bag->files);
        if (
            $removedCount > 0
            || $sensitiveCount > 0
            || $removedFileContents > 0
            || $removedSemantics > 0
            || $removedGuidelines > 0
            || $removedRepositoryContext > 0
        ) {
            Log::debug('ConfiguredPathFilter: Applied path rules', [
                'original_files' => $originalCount,
                'removed_files' => $removedCount,
                'sensitive_files' => $sensitiveCount,
                'remaining_files' => count($bag->files),
                'removed_file_contents' => $removedFileContents,
                'removed_semantics' => $removedSemantics,
                'removed_guidelines' => $removedGuidelines,
                'removed_repository_context' => $removedRepositoryContext,
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
            if ($this->matcher->matchesAny($file['filename'], $pathsConfig->sensitive)) {
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
     * Determine if a path should be included by the configured rules.
     */
    private function shouldIncludePath(string $path, PathsConfig $pathsConfig): bool
    {
        if ($pathsConfig->ignore !== [] && $this->matcher->matchesAny($path, $pathsConfig->ignore)) {
            return false;
        }

        if ($pathsConfig->include !== [] && ! $this->matcher->matchesAny($path, $pathsConfig->include)) {
            return false;
        }

        return true;
    }

    /**
     * Filter file contents using configured path rules.
     */
    private function filterFileContents(ContextBag $bag, PathsConfig $pathsConfig): int
    {
        if ($bag->fileContents === []) {
            return 0;
        }

        $before = count($bag->fileContents);

        $bag->fileContents = array_filter(
            $bag->fileContents,
            fn (string $path): bool => $this->shouldIncludePath($path, $pathsConfig),
            ARRAY_FILTER_USE_KEY
        );

        return $before - count($bag->fileContents);
    }

    /**
     * Filter semantic data using configured path rules.
     */
    private function filterSemantics(ContextBag $bag, PathsConfig $pathsConfig): int
    {
        if ($bag->semantics === []) {
            return 0;
        }

        $before = count($bag->semantics);

        $bag->semantics = array_filter(
            $bag->semantics,
            fn (string $path): bool => $this->shouldIncludePath($path, $pathsConfig),
            ARRAY_FILTER_USE_KEY
        );

        return $before - count($bag->semantics);
    }

    /**
     * Filter guidelines using configured path rules.
     */
    private function filterGuidelines(ContextBag $bag, PathsConfig $pathsConfig): int
    {
        if ($bag->guidelines === []) {
            return 0;
        }

        $before = count($bag->guidelines);

        $bag->guidelines = array_values(array_filter(
            $bag->guidelines,
            fn (array $guideline): bool => $this->shouldIncludePath($guideline['path'], $pathsConfig)
        ));

        return $before - count($bag->guidelines);
    }

    /**
     * Filter repository context using configured path rules.
     */
    private function filterRepositoryContext(ContextBag $bag, PathsConfig $pathsConfig): int
    {
        if ($bag->repositoryContext === []) {
            return 0;
        }

        $paths = $bag->metadata['repository_context_paths'] ?? null;
        if (! is_array($paths)) {
            return 0;
        }

        $removed = 0;

        if (isset($bag->repositoryContext['readme'])) {
            $readmePath = $paths['readme'] ?? null;
            if (is_string($readmePath) && ! $this->shouldIncludePath($readmePath, $pathsConfig)) {
                unset($bag->repositoryContext['readme']);
                unset($paths['readme']);
                $removed++;
            }
        }

        if (isset($bag->repositoryContext['contributing'])) {
            $contributingPath = $paths['contributing'] ?? null;
            if (is_string($contributingPath) && ! $this->shouldIncludePath($contributingPath, $pathsConfig)) {
                unset($bag->repositoryContext['contributing']);
                unset($paths['contributing']);
                $removed++;
            }
        }

        if ($paths === []) {
            unset($bag->metadata['repository_context_paths']);
        } else {
            $bag->metadata['repository_context_paths'] = $paths;
        }

        return $removed;
    }
}
