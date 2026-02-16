<?php

declare(strict_types=1);

arch('enums')
    ->expect('App\Enums')
    ->toBeEnums()
    ->toExtendNothing()
    ->toUseNothing()
    ->ignoring('App\Enums\Queue'); // Queue enum uses other enums for comparison

arch('enums are used in appropriate locations')
    ->expect('App\Enums')
    ->toOnlyBeUsedIn([
        'App\Actions',
        'App\Casts',
        'App\Console\Commands',
        'App\Events',
        'App\Http',
        'App\Jobs',
        'App\Models',
        'App\Policies',
        'App\Providers',
        'App\Services',
        'App\Support',
        'App\Filament',
        'Database\Factories',
        'Database\Seeders',
    ]);
