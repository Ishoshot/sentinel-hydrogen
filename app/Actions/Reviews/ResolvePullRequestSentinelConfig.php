<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Models\Repository;
use App\Services\SentinelConfig\ParseSentinelConfig;
use App\Services\SentinelConfig\ValueObjects\SentinelConfig;
use Illuminate\Support\Facades\Log;

final readonly class ResolvePullRequestSentinelConfig
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private FetchesSentinelConfig $fetchConfig,
        private ParseSentinelConfig $configParser,
    ) {}

    /**
     * Resolve the effective Sentinel configuration for a pull request.
     */
    public function handle(Repository $repository, string $headBranch, string $baseBranch): SentinelConfig
    {
        $branches = array_values(array_unique(array_filter([
            $headBranch,
            $baseBranch,
            $repository->default_branch,
        ])));

        foreach ($branches as $branch) {
            $fetchResult = $this->fetchConfig->handle($repository, $branch);

            if (! $fetchResult['found']) {
                continue;
            }

            if ($fetchResult['content'] === null) {
                continue;
            }

            $parseResult = $this->configParser->tryParse($fetchResult['content']);

            if (! $parseResult['success']) {
                continue;
            }

            Log::debug('ProcessPullRequestWebhook: Found sentinel config', [
                'repository' => $repository->full_name,
                'branch' => $branch,
                'tried_branches' => $branches,
            ]);

            return $parseResult['config'];
        }

        Log::debug('ProcessPullRequestWebhook: No sentinel config found, using defaults', [
            'repository' => $repository->full_name,
            'tried_branches' => $branches,
        ]);

        return SentinelConfig::default();
    }
}
