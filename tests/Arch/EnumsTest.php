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
        'App\Models',
        'App\Policies',
        'Database\Factories',
    ]);
