<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\Context\Collectors\FetchGuidelineContent;
use App\Services\GitHub\ValueObjects\RepositoryCoordinates;
use App\Services\SentinelConfig\ValueObjects\GuidelineConfig;
use Illuminate\Support\Facades\Log;

/**
 * Fetches guideline file contents in batch with limit and type enforcement.
 */
final readonly class FetchGuidelineBatch
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private FetchGuidelineContent $contentFetcher,
    ) {}

    /**
     * Fetch guideline contents up to the given limit.
     *
     * @param  array<int, GuidelineConfig>  $configs
     * @return array<int, array{path: string, description: string|null, content: string}>
     */
    public function fetch(RepositoryCoordinates $coordinates, array $configs, int $maxGuidelines): array
    {
        $guidelines = [];
        $fetchedCount = 0;

        foreach ($configs as $config) {
            if ($fetchedCount >= $maxGuidelines) {
                Log::info('GuidelinesCollector: Maximum guidelines limit reached', [
                    'limit' => $maxGuidelines,
                    'total_configured' => count($configs),
                ]);

                break;
            }

            if (! $this->contentFetcher->isAllowedFileType($config->path)) {
                Log::debug('GuidelinesCollector: Skipping unsupported file type', [
                    'path' => $config->path,
                ]);

                continue;
            }

            $content = $this->contentFetcher->fetch(
                $coordinates->installationId,
                $coordinates->owner,
                $coordinates->repo,
                $config->path
            );

            if ($content !== null) {
                $guidelines[] = [
                    'path' => $config->path,
                    'description' => $config->description,
                    'content' => $content,
                ];
                $fetchedCount++;
            }
        }

        return $guidelines;
    }
}
