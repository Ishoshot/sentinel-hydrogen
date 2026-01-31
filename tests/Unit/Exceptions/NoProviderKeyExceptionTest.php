<?php

declare(strict_types=1);

use App\Exceptions\NoProviderKeyException;

it('creates exception for no providers configured', function (): void {
    $exception = NoProviderKeyException::noProvidersConfigured();

    expect($exception)->toBeInstanceOf(NoProviderKeyException::class)
        ->and($exception->getMessage())->toBe('No provider keys configured for this repository');
});

it('creates exception for specific provider', function (): void {
    $exception = NoProviderKeyException::forProvider('openai');

    expect($exception)->toBeInstanceOf(NoProviderKeyException::class)
        ->and($exception->getMessage())->toBe('No API key configured for provider: openai');
});
