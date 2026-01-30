<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\QueueServiceProvider::class,
    App\Providers\WindsurfBoostServiceProvider::class,

    // Domain-specific providers
    App\Providers\GitHubServiceProvider::class,
    App\Providers\ReviewServiceProvider::class,
    App\Providers\CommandServiceProvider::class,
    App\Providers\BriefingServiceProvider::class,
    App\Providers\CodeIndexingServiceProvider::class,
    App\Providers\SentinelConfigServiceProvider::class,
];
