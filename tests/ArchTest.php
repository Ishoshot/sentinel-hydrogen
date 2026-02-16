<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security()->ignoring('assert')
    ->ignoring('App\Actions\Admin'); // Admin cache key hashing uses sha1

arch()->preset()->laravel()
    ->ignoring('App\Http\Controllers\Auth')
    ->ignoring('App\Http\Controllers\GitHub')
    ->ignoring('App\Http\Controllers\Team\InvitationController')
    ->ignoring('App\Http\Controllers\Notifications\NotificationController')
    ->ignoring('App\Http\Controllers\Webhooks')
    ->ignoring('App\Http\Controllers\Workspaces\WorkspaceController')
    ->ignoring('App\Enums\Briefings\BriefingPropertyType') // Enum in DTO namespace
    ->ignoring('App\Enums\Briefings\BriefingPropertyFormat') // Enum in DTO namespace
    ->ignoring('App\Exceptions\Rendering') // Exception renderers, not exceptions
    ->ignoring('App\Filament') // Filament resources follow their own conventions
    ->ignoring('App\Providers\Filament'); // Filament panel providers extend PanelProvider

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('avoid open for extension')
    ->expect('App')
    ->classes()
    ->toBeFinal()
    ->ignoring('App\Filament');

arch('ensure no extends')
    ->expect('App')
    ->classes()
    ->not->toBeAbstract()
    ->ignoring('App\Filament');

arch('property annotations')
    ->expect('App')
    ->toHavePropertiesDocumented()
    ->ignoring('App\Filament');

arch('method annotations')
    ->expect('App')
    ->toHaveMethodsDocumented()
    ->ignoring('App\Filament')
    ->ignoring('App\Providers\Filament')
    ->ignoring('App\Actions\Admin');
