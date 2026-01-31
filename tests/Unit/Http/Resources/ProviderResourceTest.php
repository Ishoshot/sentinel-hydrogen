<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Http\Resources\ProviderResource;
use App\Models\Provider;
use Illuminate\Http\Request;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('transforms a provider to array', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();

    $resource = new ProviderResource($provider);
    $request = new Request;

    $array = $resource->toArray($request);

    expect($array)->toBeArray()
        ->and($array['id'])->toBe($provider->id)
        ->and($array['type'])->toBe('github')
        ->and($array['name'])->toBe('GitHub')
        ->and($array['label'])->toBe('GitHub')
        ->and($array['icon'])->toBe('github')
        ->and($array['is_active'])->toBeTrue();
});

it('handles inactive provider', function (): void {
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $provider->update(['is_active' => false]);

    $resource = new ProviderResource($provider);
    $request = new Request;

    $array = $resource->toArray($request);

    expect($array['is_active'])->toBeFalse();
});
