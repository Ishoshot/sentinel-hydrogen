<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory as GuzzlePsrFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\GitHub\GuardedGitHubBuilderFactory;
use Tests\Support\GitHub\PreventRealGitHubRequestsPlugin;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->installGitHubNetworkGuard();
    }

    private function installGitHubNetworkGuard(): void
    {
        $this->app->singleton(PreventRealGitHubRequestsPlugin::class);

        $this->app->singleton('github.httpclientfactory', function ($app): GuardedGitHubBuilderFactory {
            $psrFactory = new GuzzlePsrFactory();

            return new GuardedGitHubBuilderFactory(
                new GuzzleClient(['connect_timeout' => 10, 'timeout' => 30]),
                $psrFactory,
                $psrFactory,
                $app->make(PreventRealGitHubRequestsPlugin::class),
            );
        });

        // Ensure factory and manager pick up the guarded builder factory.
        $this->app->forgetInstance('github.factory');
        $this->app->forgetInstance('github');
        $this->app->forgetInstance('github.connection');
    }
}
