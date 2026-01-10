<?php

declare(strict_types=1);

arch('enums')
    ->expect('App\Enums')
    ->toBeEnums()
    ->toExtendNothing()
    ->toUseNothing()
    ->toOnlyBeUsedIn([
        'App\Actions',
        'App\Console\Commands',
        'App\Http',
        'App\Jobs',
        'App\Models',
        'App\Policies',
        'App\Services',
        'Database\Factories',
    ]);
