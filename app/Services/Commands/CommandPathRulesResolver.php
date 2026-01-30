<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Models\Repository;
use App\Services\Context\SensitiveDataRedactor;
use App\Services\SentinelConfig\Contracts\SentinelConfigParser;
use App\Support\PathRuleMatcher;
use Illuminate\Support\Facades\Log;

/**
 * Resolves path rules for command execution.
 */
final readonly class CommandPathRulesResolver
{
    /**
     * Create a new CommandPathRulesResolver instance.
     */
    public function __construct(
        private FetchesSentinelConfig $fetchConfig,
        private SentinelConfigParser $configParser,
        private SensitiveDataRedactor $redactor,
        private PathRuleMatcher $matcher,
    ) {}

    /**
     * Resolve command path rules for a repository and branch.
     */
    public function resolve(Repository $repository, ?string $baseBranch = null): CommandPathRules
    {
        $pathsConfig = $this->resolvePathsConfig($repository, $baseBranch);

        return new CommandPathRules($pathsConfig, $this->redactor, $this->matcher);
    }

    /**
     * Resolve PathsConfig from sentinel config sources.
     */
    private function resolvePathsConfig(Repository $repository, ?string $baseBranch = null): PathsConfig
    {
        $config = null;

        if ($baseBranch !== null) {
            $config = $this->fetchConfigWithFallback($repository, $baseBranch, $repository->default_branch);
        }

        if ($config === null) {
            $repository->loadMissing('settings');
            $settingsConfig = $repository->settings?->sentinel_config;
            if (is_array($settingsConfig)) {
                return SentinelConfig::fromArray($settingsConfig)->getPathsOrDefault();
            }
        }

        if ($config !== null) {
            return SentinelConfig::fromArray($config)->getPathsOrDefault();
        }

        return PathsConfig::default();
    }

    /**
     * Fetch sentinel config with fallback: base_branch -> default_branch.
     *
     * @return array<string, mixed>|null
     */
    private function fetchConfigWithFallback(
        Repository $repository,
        string $baseBranch,
        ?string $defaultBranch
    ): ?array {
        $branches = array_values(array_unique(array_filter([
            $baseBranch,
            $defaultBranch,
        ])));

        foreach ($branches as $branch) {
            $config = $this->fetchAndParseConfig($repository, $branch);
            if ($config !== null) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Fetch and parse sentinel config from a specific branch.
     *
     * @return array<string, mixed>|null
     */
    private function fetchAndParseConfig(Repository $repository, string $branch): ?array
    {
        $fetchResult = $this->fetchConfig->handle($repository, $branch);

        if (! $fetchResult['found'] || $fetchResult['content'] === null) {
            return null;
        }

        $parseResult = $this->configParser->tryParse($fetchResult['content']);

        if (! $parseResult['success'] || $parseResult['config'] === null) {
            Log::warning('Command path config parse error', [
                'repository' => $repository->full_name,
                'branch' => $branch,
                'error' => $parseResult['error'] ?? 'Unknown error',
            ]);

            return null;
        }

        return $parseResult['config']->toArray();
    }
}
