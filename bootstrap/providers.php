<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\BriefingServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    App\Providers\CodeIndexingServiceProvider::class,
    App\Providers\CommandServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\GitHubServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\QueueServiceProvider::class,
    App\Providers\ReviewServiceProvider::class,
    App\Providers\SentinelConfigServiceProvider::class,
    App\Providers\SlackServiceProvider::class,
    App\Providers\WindsurfBoostServiceProvider::class,
];
