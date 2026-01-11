<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig;

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Models\Repository;
use App\Services\SentinelConfig\Contracts\SentinelConfigParser;
use Illuminate\Support\Facades\Log;

/**
 * Syncs .sentinel/config.yaml for a repository.
 *
 * Fetches the config file from GitHub, parses and validates it,
 * and stores the result in the repository settings.
 */
final readonly class SyncRepositorySentinelConfig
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private FetchesSentinelConfig $fetchConfig,
        private SentinelConfigParser $parser,
    ) {}

    /**
     * Sync .sentinel/config.yaml for a repository.
     *
     * @return array{synced: bool, config: ?SentinelConfig, error: ?string}
     */
    public function handle(Repository $repository): array
    {
        $settings = $repository->settings;

        if ($settings === null) {
            Log::warning('Repository has no settings, skipping config sync', [
                'repository_id' => $repository->id,
                'full_name' => $repository->full_name,
            ]);

            return [
                'synced' => false,
                'config' => null,
                'error' => 'Repository has no settings',
            ];
        }

        // Fetch the config file from GitHub
        $fetchResult = $this->fetchConfig->handle($repository);

        // If there was a fetch error (not just "not found"), record it
        if ($fetchResult['error'] !== null && $fetchResult['found'] === false) {
            $settings->update([
                'config_synced_at' => now(),
                'config_error' => $fetchResult['error'],
            ]);

            return [
                'synced' => false,
                'config' => null,
                'error' => $fetchResult['error'],
            ];
        }

        // If config file doesn't exist, clear any existing config
        if (! $fetchResult['found']) {
            $settings->update([
                'sentinel_config' => null,
                'config_synced_at' => now(),
                'config_error' => null,
            ]);

            Log::debug('No sentinel config found, cleared existing config', [
                'repository' => $repository->full_name,
            ]);

            return [
                'synced' => true,
                'config' => null,
                'error' => null,
            ];
        }

        // Parse and validate the config
        $parseResult = $this->parser->tryParse($fetchResult['content'] ?? '');

        if (! $parseResult['success']) {
            // Store the error but keep any existing valid config
            $settings->update([
                'config_synced_at' => now(),
                'config_error' => $parseResult['error'],
            ]);

            Log::warning('Sentinel config parse error', [
                'repository' => $repository->full_name,
                'error' => $parseResult['error'],
            ]);

            return [
                'synced' => false,
                'config' => null,
                'error' => $parseResult['error'],
            ];
        }

        // Successfully parsed - store the config
        /** @var SentinelConfig $config */
        $config = $parseResult['config'];

        $settings->update([
            'sentinel_config' => $config->toArray(),
            'config_synced_at' => now(),
            'config_error' => null,
        ]);

        Log::info('Sentinel config synced successfully', [
            'repository' => $repository->full_name,
            'version' => $config->version,
        ]);

        return [
            'synced' => true,
            'config' => $config,
            'error' => null,
        ];
    }
}
