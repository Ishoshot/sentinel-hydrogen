<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\Support\GuidelineBatchFetcher;
use App\Services\Context\Collectors\GuidelineContentFetcher;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Resolvers\RepositoryCoordinatesResolver;
use Illuminate\Support\Facades\Log;

/**
 * Collects custom guideline files defined in .sentinel/config.yaml.
 *
 * Fetches team-specific documentation files that help the AI understand
 * project conventions, coding standards, and custom review guidelines.
 */
final readonly class GuidelinesCollector implements ContextCollector
{
    /**
     * Maximum number of guideline files to fetch.
     */
    private const int MAX_GUIDELINES = 5;

    /**
     * Create a new GuidelinesCollector instance.
     */
    public function __construct(
        private GuidelineContentFetcher $contentFetcher,
        private RepositoryCoordinatesResolver $coordinatesResolver = new RepositoryCoordinatesResolver,
        private ?GuidelineBatchFetcher $batchFetcher = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'guidelines';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 45; // After RepositoryContextCollector (50), before filters
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(array $params): bool
    {
        return isset($params['repository'], $params['run'])
            && $params['repository'] instanceof Repository
            && $params['run'] instanceof Run;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        $guidelineConfigs = $this->getGuidelinesConfig($bag);

        if ($guidelineConfigs === []) {
            return;
        }

        $coordinates = $this->coordinatesResolver->resolve($repository);

        if (! $coordinates instanceof \App\Services\GitHub\ValueObjects\RepositoryCoordinates) {
            Log::warning('GuidelinesCollector: Repository has no installation', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        $bag->guidelines = $this->batchFetcher()->fetch($coordinates, $guidelineConfigs, self::MAX_GUIDELINES);

        Log::info('GuidelinesCollector: Collected guidelines', [
            'repository' => $coordinates->fullName,
            'configured' => count($guidelineConfigs),
            'fetched' => count($bag->guidelines),
        ]);
    }

    /**
     * Get guidelines configuration from the context bag metadata.
     *
     * @return array<int, \App\DataTransferObjects\SentinelConfig\GuidelineConfig>
     */
    private function getGuidelinesConfig(ContextBag $bag): array
    {
        $sentinelConfigData = $bag->metadata['sentinel_config'] ?? null;

        if (! is_array($sentinelConfigData)) {
            return [];
        }

        /** @var array<string, mixed> $sentinelConfigData */
        $sentinelConfig = SentinelConfig::fromArray($sentinelConfigData);

        return $sentinelConfig->guidelines;
    }

    /**
     * Resolve the batch fetcher dependency.
     */
    private function batchFetcher(): GuidelineBatchFetcher
    {
        return $this->batchFetcher ?? new GuidelineBatchFetcher($this->contentFetcher);
    }
}
