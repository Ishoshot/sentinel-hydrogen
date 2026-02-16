<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Enums\Auth\ProviderType;
use App\Models\AiOption;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\Reviews\Contracts\ProviderKeyResolver;
use App\Services\Reviews\Resolvers\PrismReviewProviderResolver;
use App\Services\SentinelConfig\ValueObjects\ProviderConfig;
use Prism\Prism\Enums\Provider as PrismProvider;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createResolverRepository(): Repository
{
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => fake()->unique()->randomNumber(8),
    ]);

    return Repository::factory()->forInstallation($installation)->create();
}

// --- resolveProviderConfig ---

it('resolves provider config from policy snapshot with provider data', function (): void {
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);

    $snapshot = [
        'provider' => [
            'preferred' => 'anthropic',
            'model' => 'claude-sonnet-4-5-20250929',
            'fallback' => true,
        ],
    ];

    $config = $resolver->resolveProviderConfig($snapshot);

    expect($config)->toBeInstanceOf(ProviderConfig::class)
        ->and($config->preferred)->toBe(AiProvider::Anthropic)
        ->and($config->model)->toBe('claude-sonnet-4-5-20250929')
        ->and($config->fallback)->toBeTrue();
});

it('returns default provider config when snapshot has no provider key', function (): void {
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);

    $config = $resolver->resolveProviderConfig([]);

    expect($config)->toBeInstanceOf(ProviderConfig::class)
        ->and($config->preferred)->toBeNull()
        ->and($config->model)->toBeNull()
        ->and($config->fallback)->toBeFalse();
});

it('returns default provider config when snapshot provider is not an array', function (): void {
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);

    $config = $resolver->resolveProviderConfig(['provider' => 'invalid-string']);

    expect($config)->toBeInstanceOf(ProviderConfig::class)
        ->and($config->preferred)->toBeNull();
});

// --- getProvidersToTry ---

it('returns empty array when no providers are available', function (): void {
    $repository = createResolverRepository();
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $keyResolver->shouldReceive('getAvailableProviders')->with($repository)->andReturn([]);

    $resolver = new PrismReviewProviderResolver($keyResolver);
    $config = new ProviderConfig();

    expect($resolver->getProvidersToTry($repository, $config))->toBe([]);
});

it('returns available providers when no preference is set', function (): void {
    $repository = createResolverRepository();
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $keyResolver->shouldReceive('getAvailableProviders')
        ->with($repository)
        ->andReturn([AiProvider::Anthropic, AiProvider::OpenAI]);

    $resolver = new PrismReviewProviderResolver($keyResolver);
    $config = new ProviderConfig();

    $providers = $resolver->getProvidersToTry($repository, $config);

    expect($providers)->toBe([AiProvider::Anthropic, AiProvider::OpenAI]);
});

it('puts preferred provider first when available', function (): void {
    $repository = createResolverRepository();
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $keyResolver->shouldReceive('getAvailableProviders')
        ->with($repository)
        ->andReturn([AiProvider::Anthropic, AiProvider::OpenAI]);
    $keyResolver->shouldReceive('hasProvider')
        ->with($repository, AiProvider::OpenAI)
        ->andReturn(true);

    $resolver = new PrismReviewProviderResolver($keyResolver);
    $config = new ProviderConfig(preferred: AiProvider::OpenAI);

    $providers = $resolver->getProvidersToTry($repository, $config);

    expect($providers[0])->toBe(AiProvider::OpenAI)
        ->and($providers)->toContain(AiProvider::Anthropic);
});

it('returns empty array when preferred provider is unavailable and fallback is disabled', function (): void {
    $repository = createResolverRepository();
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $keyResolver->shouldReceive('getAvailableProviders')
        ->with($repository)
        ->andReturn([AiProvider::OpenAI]);
    $keyResolver->shouldReceive('hasProvider')
        ->with($repository, AiProvider::Anthropic)
        ->andReturn(false);

    $resolver = new PrismReviewProviderResolver($keyResolver);
    $config = new ProviderConfig(preferred: AiProvider::Anthropic, fallback: false);

    expect($resolver->getProvidersToTry($repository, $config))->toBe([]);
});

it('falls back to available providers when preferred is unavailable and fallback is enabled', function (): void {
    $repository = createResolverRepository();
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $keyResolver->shouldReceive('getAvailableProviders')
        ->with($repository)
        ->andReturn([AiProvider::OpenAI]);
    $keyResolver->shouldReceive('hasProvider')
        ->with($repository, AiProvider::Anthropic)
        ->andReturn(false);

    $resolver = new PrismReviewProviderResolver($keyResolver);
    $config = new ProviderConfig(preferred: AiProvider::Anthropic, fallback: true);

    expect($resolver->getProvidersToTry($repository, $config))->toBe([AiProvider::OpenAI]);
});

// --- mapToProvider ---

it('maps anthropic to prism anthropic provider', function (): void {
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);

    expect($resolver->mapToProvider(AiProvider::Anthropic))->toBe(PrismProvider::Anthropic);
});

it('maps openai to prism openai provider', function (): void {
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);

    expect($resolver->mapToProvider(AiProvider::OpenAI))->toBe(PrismProvider::OpenAI);
});

// --- getProviderKey ---

it('returns provider key when one exists', function (): void {
    $repository = createResolverRepository();
    $workspace = $repository->workspace;
    $providerKey = ProviderKey::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'provider' => AiProvider::Anthropic,
    ]);

    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $keyResolver->shouldReceive('getProviderKey')
        ->with($repository, AiProvider::Anthropic)
        ->andReturn($providerKey);

    $resolver = new PrismReviewProviderResolver($keyResolver);
    $result = $resolver->getProviderKey($repository, AiProvider::Anthropic);

    expect($result)->toBeInstanceOf(ProviderKey::class)
        ->and($result->id)->toBe($providerKey->id);
});

it('returns null when no provider key exists', function (): void {
    $repository = createResolverRepository();
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $keyResolver->shouldReceive('getProviderKey')
        ->with($repository, AiProvider::Anthropic)
        ->andReturnNull();

    $resolver = new PrismReviewProviderResolver($keyResolver);

    expect($resolver->getProviderKey($repository, AiProvider::Anthropic))->toBeNull();
});

// --- resolveModel ---

it('resolves model from provider key when provider model is set', function (): void {
    $repository = createResolverRepository();
    $aiOption = AiOption::factory()->anthropicSonnet45()->create();
    $providerKey = ProviderKey::factory()->create([
        'workspace_id' => $repository->workspace_id,
        'repository_id' => $repository->id,
        'provider' => AiProvider::Anthropic,
        'provider_model_id' => $aiOption->id,
    ]);

    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);
    $config = new ProviderConfig(preferred: AiProvider::Anthropic);

    $model = $resolver->resolveModel(AiProvider::Anthropic, $config, $providerKey);

    expect($model)->toBe($aiOption->identifier);
});

it('resolves model from provider config when preferred matches and no provider model', function (): void {
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);

    $config = new ProviderConfig(
        preferred: AiProvider::Anthropic,
        model: 'claude-custom-model',
    );

    $model = $resolver->resolveModel(AiProvider::Anthropic, $config, null);

    expect($model)->toBe('claude-custom-model');
});

it('does not use config model when preferred provider does not match', function (): void {
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);

    $config = new ProviderConfig(
        preferred: AiProvider::Anthropic,
        model: 'claude-custom-model',
    );

    $model = $resolver->resolveModel(AiProvider::OpenAI, $config, null);

    // Should fallback to default or hardcoded value, not the config model
    expect($model)->not->toBe('claude-custom-model');
});

it('resolves model from ai option default when available', function (): void {
    $aiOption = AiOption::factory()->openaiGpt4o()->create();

    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);
    $config = new ProviderConfig();

    $model = $resolver->resolveModel(AiProvider::OpenAI, $config, null);

    expect($model)->toBe($aiOption->identifier);
});

it('falls back to hardcoded anthropic model when no other source exists', function (): void {
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);
    $config = new ProviderConfig();

    $model = $resolver->resolveModel(AiProvider::Anthropic, $config, null);

    expect($model)->toBe('claude-sonnet-4-5-20250929');
});

it('falls back to hardcoded openai model when no other source exists', function (): void {
    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $resolver = new PrismReviewProviderResolver($keyResolver);
    $config = new ProviderConfig();

    $model = $resolver->resolveModel(AiProvider::OpenAI, $config, null);

    expect($model)->toBe('gpt-4o');
});
