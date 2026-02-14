<?php

declare(strict_types=1);

use App\Services\SentinelMessageCatalogLoader;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget('sentinel:messages');
});

it('loads greeting catalog with expected keys', function (): void {
    $loader = new SentinelMessageCatalogLoader;
    $catalog = $loader->load();

    expect($catalog)
        ->toHaveKeys(['greetings', 'branding'])
        ->and($catalog['greetings'])->toBeArray()->not->toBeEmpty()
        ->and($catalog['branding'])->toBeArray()->not->toBeEmpty();
});

it('reloads from file when cache payload structure is invalid', function (): void {
    Cache::put('sentinel:messages', ['invalid' => 'payload'], 3600);

    $loader = new SentinelMessageCatalogLoader;
    $catalog = $loader->load();

    expect($catalog)
        ->toHaveKeys(['greetings', 'branding'])
        ->and($catalog['greetings'])->toBeArray()
        ->and($catalog['branding'])->toBeArray();
});
