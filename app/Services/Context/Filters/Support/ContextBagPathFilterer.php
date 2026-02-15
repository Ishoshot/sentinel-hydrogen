<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\ContextBag;
use App\Services\SentinelConfig\ValueObjects\PathsConfig;

/**
 * Applies configured path rules to all path-keyed sections of a ContextBag.
 */
final readonly class ContextBagPathFilterer
{
    /**
     * Create a new ContextBagPathFilterer instance.
     */
    public function __construct(
        private ConfiguredPathInclusionDecider $inclusionDecider,
    ) {}

    /**
     * Filter all path-keyed bag sections using configured path rules.
     *
     * @return array{removed_files: int, removed_file_contents: int, removed_semantics: int, removed_guidelines: int}
     */
    public function filter(ContextBag $bag, PathsConfig $pathsConfig): array
    {
        $originalFileCount = count($bag->files);

        $this->filterFiles($bag, $pathsConfig);

        return [
            'removed_files' => $originalFileCount - count($bag->files),
            'removed_file_contents' => $this->filterFileContents($bag, $pathsConfig),
            'removed_semantics' => $this->filterSemantics($bag, $pathsConfig),
            'removed_guidelines' => $this->filterGuidelines($bag, $pathsConfig),
        ];
    }

    /**
     * Filter changed file entries in the context bag.
     */
    private function filterFiles(ContextBag $bag, PathsConfig $pathsConfig): void
    {
        $bag->files = array_values(array_filter(
            $bag->files,
            fn (array $file): bool => $this->inclusionDecider->shouldInclude($file['filename'], $pathsConfig)
        ));
    }

    /**
     * Filter full-file content entries keyed by path.
     */
    private function filterFileContents(ContextBag $bag, PathsConfig $pathsConfig): int
    {
        if ($bag->fileContents === []) {
            return 0;
        }

        $before = count($bag->fileContents);

        $bag->fileContents = array_filter(
            $bag->fileContents,
            fn (string $path): bool => $this->inclusionDecider->shouldInclude($path, $pathsConfig),
            ARRAY_FILTER_USE_KEY
        );

        return $before - count($bag->fileContents);
    }

    /**
     * Filter semantic-analysis entries keyed by path.
     */
    private function filterSemantics(ContextBag $bag, PathsConfig $pathsConfig): int
    {
        if ($bag->semantics === []) {
            return 0;
        }

        $before = count($bag->semantics);

        $bag->semantics = array_filter(
            $bag->semantics,
            fn (string $path): bool => $this->inclusionDecider->shouldInclude($path, $pathsConfig),
            ARRAY_FILTER_USE_KEY
        );

        return $before - count($bag->semantics);
    }

    /**
     * Filter guideline entries by guideline path.
     */
    private function filterGuidelines(ContextBag $bag, PathsConfig $pathsConfig): int
    {
        if ($bag->guidelines === []) {
            return 0;
        }

        $before = count($bag->guidelines);

        $bag->guidelines = array_values(array_filter(
            $bag->guidelines,
            fn (array $guideline): bool => $this->inclusionDecider->shouldInclude($guideline['path'], $pathsConfig)
        ));

        return $before - count($bag->guidelines);
    }
}
