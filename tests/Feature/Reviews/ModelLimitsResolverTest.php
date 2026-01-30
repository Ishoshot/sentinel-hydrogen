<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Models\AiOption;
use App\Services\Reviews\ModelLimitsResolver;
use Illuminate\Support\Facades\Cache;

it('returns model limits from the database when available', function (): void {
    Cache::flush();

    AiOption::factory()->create([
        'provider' => AiProvider::OpenAI,
        'identifier' => 'gpt-4o',
        'context_window_tokens' => 50000,
        'max_output_tokens' => 2048,
    ]);

    $resolver = new ModelLimitsResolver();
    $limits = $resolver->resolve(AiProvider::OpenAI, 'gpt-4o');

    expect($limits->contextWindowTokens)->toBe(50000)
        ->and($limits->maxOutputTokens)->toBe(2048);
});

it('falls back to safe defaults when no model is found', function (): void {
    Cache::flush();

    $resolver = new ModelLimitsResolver();
    $limits = $resolver->resolve(AiProvider::OpenAI, 'unknown-model');

    expect($limits->contextWindowTokens)->toBe(32000)
        ->and($limits->maxOutputTokens)->toBe(4096);
});
