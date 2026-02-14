<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use App\Services\Context\Filters\Support\ConfiguredPathInclusionDecider;
use App\Services\Context\Filters\Support\ConfiguredPathRepositoryContextFilter;
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
    private ConfiguredPathInclusionDecider $pathInclusionDecider;

    private ConfiguredPathRepositoryContextFilter $repositoryContextFilter;

    /**
     * Create a new ConfiguredPathFilter instance.
     */
    public function __construct(PathRuleMatcher $matcher)
    {
        $this->pathInclusionDecider = new ConfiguredPathInclusionDecider($matcher);
        $this->repositoryContextFilter = new ConfiguredPathRepositoryContextFilter($this->pathInclusionDecider);
    }

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

        $this->filterFiles($bag, $pathsConfig);

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
            if (Log::getFacadeRoot() === null) {
                return;
            }

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
            if ($this->pathInclusionDecider->isSensitive($file['filename'], $pathsConfig)) {
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
     * Filter files using configured path rules.
     */
    private function filterFiles(ContextBag $bag, PathsConfig $pathsConfig): void
    {
        $bag->files = array_values(array_filter(
            $bag->files,
            fn (array $file): bool => $this->pathInclusionDecider->shouldInclude($file['filename'], $pathsConfig)
        ));
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
            fn (string $path): bool => $this->pathInclusionDecider->shouldInclude($path, $pathsConfig),
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
            fn (string $path): bool => $this->pathInclusionDecider->shouldInclude($path, $pathsConfig),
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
            fn (array $guideline): bool => $this->pathInclusionDecider->shouldInclude($guideline['path'], $pathsConfig)
        ));

        return $before - count($bag->guidelines);
    }

    /**
     * Filter repository context using configured path rules.
     */
    private function filterRepositoryContext(ContextBag $bag, PathsConfig $pathsConfig): int
    {
        $result = $this->repositoryContextFilter->filter($bag->repositoryContext, $bag->metadata, $pathsConfig);

        $bag->repositoryContext = $result['repository_context'];
        $bag->metadata = $result['metadata'];

        return $result['removed'];
    }
}
