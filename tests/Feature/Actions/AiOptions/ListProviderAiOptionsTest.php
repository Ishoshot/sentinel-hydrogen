<?php

declare(strict_types=1);

use App\Actions\AiOptions\ListProviderAiOptions;
use App\Enums\AI\AiProvider;
use App\Models\AiOption;

it('lists active AI options for a provider', function (): void {
    AiOption::factory()->count(2)->create([
        'provider' => AiProvider::Anthropic,
        'is_active' => true,
    ]);

    $action = new ListProviderAiOptions;
    $result = $action->handle(AiProvider::Anthropic);

    expect($result)->toHaveCount(2);
});

it('only includes active options', function (): void {
    AiOption::factory()->create([
        'provider' => AiProvider::Anthropic,
        'is_active' => true,
    ]);
    AiOption::factory()->create([
        'provider' => AiProvider::Anthropic,
        'is_active' => false,
    ]);

    $action = new ListProviderAiOptions;
    $result = $action->handle(AiProvider::Anthropic);

    expect($result)->toHaveCount(1);
});

it('only includes options for the specified provider', function (): void {
    AiOption::factory()->create([
        'provider' => AiProvider::Anthropic,
        'is_active' => true,
    ]);
    AiOption::factory()->create([
        'provider' => AiProvider::OpenAI,
        'is_active' => true,
    ]);

    $action = new ListProviderAiOptions;
    $result = $action->handle(AiProvider::Anthropic);

    expect($result)->toHaveCount(1);
    expect($result->first()->provider)->toBe(AiProvider::Anthropic);
});

it('orders by sort_order then name', function (): void {
    AiOption::factory()->create([
        'provider' => AiProvider::Anthropic,
        'is_active' => true,
        'sort_order' => 2,
        'name' => 'Model B',
    ]);
    AiOption::factory()->create([
        'provider' => AiProvider::Anthropic,
        'is_active' => true,
        'sort_order' => 1,
        'name' => 'Model A',
    ]);
    AiOption::factory()->create([
        'provider' => AiProvider::Anthropic,
        'is_active' => true,
        'sort_order' => 1,
        'name' => 'Model C',
    ]);

    $action = new ListProviderAiOptions;
    $result = $action->handle(AiProvider::Anthropic);

    expect($result->pluck('name')->toArray())->toBe(['Model A', 'Model C', 'Model B']);
});

it('returns empty collection when no options exist', function (): void {
    $action = new ListProviderAiOptions;
    $result = $action->handle(AiProvider::Anthropic);

    expect($result)->toBeEmpty();
});
