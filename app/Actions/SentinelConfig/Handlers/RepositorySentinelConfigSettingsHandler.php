<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Handlers;

use App\Models\RepositorySettings;
use App\Services\SentinelConfig\ValueObjects\SentinelConfig;

final class RepositorySentinelConfigSettingsHandler
{
    /**
     * Persist a fetch error on repository settings.
     */
    public function markFetchError(RepositorySettings $settings, string $error): void
    {
        $settings->update([
            'config_synced_at' => now(),
            'config_error' => $error,
        ]);
    }

    /**
     * Clear sentinel config when no config file is found.
     */
    public function clearConfig(RepositorySettings $settings): void
    {
        $settings->update([
            'sentinel_config' => null,
            'config_synced_at' => now(),
            'config_error' => null,
        ]);
    }

    /**
     * Persist a parse error while preserving existing valid config.
     */
    public function markParseError(RepositorySettings $settings, string $error): void
    {
        $settings->update([
            'config_synced_at' => now(),
            'config_error' => $error,
        ]);
    }

    /**
     * Persist parsed sentinel config and current sync status.
     */
    public function saveConfig(RepositorySettings $settings, SentinelConfig $config, ?string $configError): void
    {
        $settings->update([
            'sentinel_config' => $config->toArray(),
            'config_synced_at' => now(),
            'config_error' => $configError,
        ]);
    }

    /**
     * Opportunistically persist config when the stored config is empty.
     *
     * Called during PR reviews to heal from initial sync failures.
     * Does nothing if config is already stored.
     *
     * @param  array<string, mixed>  $config
     */
    public function healIfEmpty(RepositorySettings $settings, array $config): void
    {
        if ($settings->sentinel_config !== null) {
            return;
        }

        $settings->update([
            'sentinel_config' => $config,
            'config_synced_at' => now(),
            'config_error' => null,
        ]);
    }
}
