<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use App\Services\Context\Filters\Support\ConfiguredPathInclusionDecider;
use App\Services\Context\Filters\Support\ConfiguredPathRepositoryContextFilter;
use App\Services\Context\Filters\Support\ContextBagPathFilterer;
use App\Services\Context\Filters\Support\SensitiveFileMarker;
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
    private ContextBagPathFilterer $bagPathFilterer;

    private SensitiveFileMarker $sensitiveFileMarker;

    private ConfiguredPathRepositoryContextFilter $repositoryContextFilter;

    /**
     * Create a new ConfiguredPathFilter instance.
     */
    public function __construct(PathRuleMatcher $matcher)
    {
        $inclusionDecider = new ConfiguredPathInclusionDecider($matcher);
        $this->bagPathFilterer = new ContextBagPathFilterer($inclusionDecider);
        $this->sensitiveFileMarker = new SensitiveFileMarker($inclusionDecider);
        $this->repositoryContextFilter = new ConfiguredPathRepositoryContextFilter($inclusionDecider);
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

        $counts = $this->bagPathFilterer->filter($bag, $pathsConfig);
        $sensitiveCount = $this->sensitiveFileMarker->mark($bag, $pathsConfig);
        $removedRepositoryContext = $this->filterRepositoryContext($bag, $pathsConfig);
        $bag->recalculateMetrics();

        if (
            $counts['removed_files'] > 0
            || $sensitiveCount > 0
            || $counts['removed_file_contents'] > 0
            || $counts['removed_semantics'] > 0
            || $counts['removed_guidelines'] > 0
            || $removedRepositoryContext > 0
        ) {
            Log::debug('ConfiguredPathFilter: Applied path rules', [
                'original_files' => $counts['removed_files'] + count($bag->files),
                'removed_files' => $counts['removed_files'],
                'sensitive_files' => $sensitiveCount,
                'remaining_files' => count($bag->files),
                'removed_file_contents' => $counts['removed_file_contents'],
                'removed_semantics' => $counts['removed_semantics'],
                'removed_guidelines' => $counts['removed_guidelines'],
                'removed_repository_context' => $removedRepositoryContext,
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
