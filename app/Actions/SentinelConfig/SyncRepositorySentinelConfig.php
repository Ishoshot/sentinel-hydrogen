<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig;

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Actions\SentinelConfig\Handlers\RepositorySentinelConfigGuidelineHandler;
use App\Actions\SentinelConfig\Handlers\RepositorySentinelConfigSettingsHandler;
use App\Models\Repository;
use App\Services\SentinelConfig\ParseSentinelConfig;
use App\Services\SentinelConfig\ValueObjects\SentinelConfig;
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
        private ParseSentinelConfig $parser,
        private RepositorySentinelConfigSettingsHandler $settingsHandler,
        private RepositorySentinelConfigGuidelineHandler $guidelineHandler,
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
        if ($fetchResult->error !== null && $fetchResult->found === false) {
            $this->settingsHandler->markFetchError($settings, $fetchResult->error);

            return [
                'synced' => false,
                'config' => null,
                'error' => $fetchResult->error,
            ];
        }

        // If config file doesn't exist, clear any existing config
        if (! $fetchResult->found) {
            $this->settingsHandler->clearConfig($settings);

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
        $parseResult = $this->parser->tryParse($fetchResult->content ?? '');

        if (! $parseResult['success']) {
            $parseError = $parseResult['error'];

            // Store the error but keep any existing valid config
            $this->settingsHandler->markParseError($settings, $parseError);

            Log::warning('Sentinel config parse error', [
                'repository' => $repository->full_name,
                'error' => $parseError,
            ]);

            return [
                'synced' => false,
                'config' => null,
                'error' => $parseError,
            ];
        }

        // Successfully parsed - store the config
        $config = $parseResult['config'];

        $guidelineResult = $this->guidelineHandler->apply($repository, $config);
        $config = $guidelineResult['config'];
        $configError = $guidelineResult['error'];

        $this->settingsHandler->saveConfig($settings, $config, $configError);

        Log::info('Sentinel config synced successfully', [
            'repository' => $repository->full_name,
            'version' => $config->version,
        ]);

        return [
            'synced' => true,
            'config' => $config,
            'error' => $configError,
        ];
    }
}
