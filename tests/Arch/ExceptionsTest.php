<?php

declare(strict_types=1);

arch('exceptions')
    ->expect('App\Exceptions')
    ->not->toBeUsed()
    ->ignoring([
        'App\Exceptions\Rendering',
        'App\Actions',
        'App\Console\Commands',
        'App\Http\Controllers',
        'App\Services',
    ]);

arch('exception classes implement throwable')
    ->expect('App\Exceptions\NoProviderKeyException')
    ->toImplement('Throwable');

arch('exception renderers implement contract')
    ->expect('App\Exceptions\Rendering\InvalidArgumentJsonRenderer')
    ->toImplement('App\Exceptions\Rendering\ExceptionRenderer');

arch('exception renderers implement contract - oauth')
    ->expect('App\Exceptions\Rendering\OAuthCallbackRedirectRenderer')
    ->toImplement('App\Exceptions\Rendering\ExceptionRenderer');

arch('exception renderers implement contract - webhook')
    ->expect('App\Exceptions\Rendering\WebhookSignatureRenderer')
    ->toImplement('App\Exceptions\Rendering\ExceptionRenderer');

arch('exception renderers implement contract - billing')
    ->expect('App\Exceptions\Rendering\BillingRuntimeExceptionRenderer')
    ->toImplement('App\Exceptions\Rendering\ExceptionRenderer');
