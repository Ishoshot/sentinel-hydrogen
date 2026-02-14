<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\DataTransferObjects\SentinelConfig\PathsConfig;

/**
 * Applies configured path rules to repository context sections.
 */
final readonly class ConfiguredPathRepositoryContextFilter
{
    /**
     * Create a new repository context filter instance.
     */
    public function __construct(private ConfiguredPathInclusionDecider $inclusionDecider) {}

    /**
     * Filter repository context and related metadata paths.
     *
     * @param  array{readme?: string|null, contributing?: string|null}  $repositoryContext
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     repository_context: array{readme?: string|null, contributing?: string|null},
     *     metadata: array<string, mixed>,
     *     removed: int
     * }
     */
    public function filter(array $repositoryContext, array $metadata, PathsConfig $pathsConfig): array
    {
        if ($repositoryContext === []) {
            return [
                'repository_context' => $repositoryContext,
                'metadata' => $metadata,
                'removed' => 0,
            ];
        }

        $paths = $metadata['repository_context_paths'] ?? null;
        if (! is_array($paths)) {
            return [
                'repository_context' => $repositoryContext,
                'metadata' => $metadata,
                'removed' => 0,
            ];
        }

        /** @var array<string, mixed> $paths */
        $removed = 0;
        $repositoryContext = $this->filterSection($repositoryContext, $paths, $pathsConfig, 'readme', $removed);
        $repositoryContext = $this->filterSection($repositoryContext, $paths, $pathsConfig, 'contributing', $removed);

        if ($paths === []) {
            unset($metadata['repository_context_paths']);
        } else {
            $metadata['repository_context_paths'] = $paths;
        }

        return [
            'repository_context' => $repositoryContext,
            'metadata' => $metadata,
            'removed' => $removed,
        ];
    }

    /**
     * @param  array{readme?: string|null, contributing?: string|null}  $repositoryContext
     * @param  array<string, mixed>  $paths
     * @return array{readme?: string|null, contributing?: string|null}
     */
    private function filterSection(
        array $repositoryContext,
        array &$paths,
        PathsConfig $pathsConfig,
        string $section,
        int &$removed,
    ): array {
        if (! isset($repositoryContext[$section])) {
            return $repositoryContext;
        }

        $sectionPath = $paths[$section] ?? null;
        if (! is_string($sectionPath)) {
            return $repositoryContext;
        }

        if ($this->inclusionDecider->shouldInclude($sectionPath, $pathsConfig)) {
            return $repositoryContext;
        }

        unset($repositoryContext[$section], $paths[$section]);
        $removed++;

        return $repositoryContext;
    }
}
