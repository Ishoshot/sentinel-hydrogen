<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Models\Repository;
use App\Services\SentinelConfig\ParseSentinelConfig;
use Illuminate\Support\Facades\Log;

/**
 * Fetches and parses sentinel config with ordered branch fallback.
 */
final readonly class FetchSentinelConfigBranch
{
    /**
     * Create a new FetchSentinelConfigBranch instance.
     */
    public function __construct(
        private FetchesSentinelConfig $fetchConfig,
        private ParseSentinelConfig $configParser,
    ) {}

    /**
     * Fetch sentinel config with fallback: base_branch -> default_branch.
     *
     * @return array{config: array<string, mixed>|null, branch: string|null}
     */
    public function fetch(Repository $repository, ?string $baseBranch, ?string $defaultBranch): array
    {
        $branches = array_values(array_unique(array_filter([
            $baseBranch,
            $defaultBranch,
        ])));

        foreach ($branches as $branch) {
            $config = $this->fetchAndParse($repository, $branch);

            if ($config !== null) {
                Log::debug('DiffCollector: Found sentinel config', [
                    'repository' => $repository->full_name,
                    'branch' => $branch,
                    'tried_branches' => $branches,
                ]);

                return ['config' => $config, 'branch' => $branch];
            }
        }

        Log::debug('DiffCollector: No sentinel config found in any branch', [
            'repository' => $repository->full_name,
            'tried_branches' => $branches,
        ]);

        return ['config' => null, 'branch' => null];
    }

    /**
     * Fetch and parse sentinel config from a specific branch.
     *
     * @return array<string, mixed>|null
     */
    private function fetchAndParse(Repository $repository, string $branch): ?array
    {
        $fetchResult = $this->fetchConfig->handle($repository, $branch);

        if (! $fetchResult['found'] || $fetchResult['content'] === null) {
            return null;
        }

        $parseResult = $this->configParser->tryParse($fetchResult['content']);

        if (! $parseResult['success']) {
            Log::warning('DiffCollector: Failed to parse sentinel config', [
                'repository' => $repository->full_name,
                'branch' => $branch,
                'error' => $parseResult['error'],
            ]);

            return null;
        }

        return $parseResult['config']->toArray();
    }
}
