<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Actions\SentinelConfig\FetchSentinelConfig;
use App\Services\SentinelConfig\Contracts\SentinelConfigParser;
use App\Services\SentinelConfig\SentinelConfigParserService;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Service provider for Sentinel configuration services.
 *
 * Registers services for fetching and parsing repository
 * sentinel.yaml configuration files.
 */
final class SentinelConfigServiceProvider extends ServiceProvider
{
    /**
     * Register Sentinel config services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(FetchesSentinelConfig::class, FetchSentinelConfig::class);
        $this->app->bind(SentinelConfigParser::class, SentinelConfigParserService::class);
    }
}
