<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security()->ignoring('assert');

arch()->preset()->laravel()
    ->ignoring('App\Http\Controllers\Auth')
    ->ignoring('App\Http\Controllers\GitHub')
    ->ignoring('App\Http\Controllers\Team\InvitationController')
    ->ignoring('App\Http\Controllers\Notifications\NotificationController')
    ->ignoring('App\Http\Controllers\Webhooks')
    ->ignoring('App\Http\Controllers\Workspaces\WorkspaceController')
    ->ignoring('App\Enums\Briefings\BriefingPropertyType') // Enum in DTO namespace
    ->ignoring('App\Enums\Briefings\BriefingPropertyFormat') // Enum in DTO namespace
    ->ignoring('App\Exceptions\Rendering'); // Exception renderers, not exceptions

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('avoid open for extension')
    ->expect('App')
    ->classes()
    ->toBeFinal();

arch('ensure no extends')
    ->expect('App')
    ->classes()
    ->not->toBeAbstract();

arch('annotations')
    ->expect('App')
    ->toHavePropertiesDocumented()
    ->toHaveMethodsDocumented();
