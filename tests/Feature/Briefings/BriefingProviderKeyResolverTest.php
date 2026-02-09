<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Models\AiOption;
use App\Models\ProviderKey;
use App\Models\Workspace;
use App\Services\Briefings\BriefingProviderKeyResolver;
use App\Services\Briefings\ValueObjects\BriefingAiConfiguration;

beforeEach(function (): void {
    $this->resolver = app(BriefingProviderKeyResolver::class);
    $this->workspace = Workspace::factory()->create();
});

describe('resolve', function (): void {
    it('returns decrypted key for workspace-level provider key', function (): void {
        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->anthropic()
            ->create();

        $key = $this->resolver->resolve($this->workspace, AiProvider::Anthropic);

        expect($key)->not()->toBeNull()
            ->and($key)->toStartWith('sk-ant-test-');
    });

    it('returns null when no workspace-level key exists', function (): void {
        $key = $this->resolver->resolve($this->workspace, AiProvider::Anthropic);

        expect($key)->toBeNull();
    });

    it('does not return repo-level keys', function (): void {
        // Create a repo-level key (not workspace-level)
        ProviderKey::factory()
            ->anthropic()
            ->create(['workspace_id' => $this->workspace->id]);

        $key = $this->resolver->resolve($this->workspace, AiProvider::Anthropic);

        expect($key)->toBeNull();
    });

    it('returns null when provider does not match', function (): void {
        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->openai()
            ->create();

        $key = $this->resolver->resolve($this->workspace, AiProvider::Anthropic);

        expect($key)->toBeNull();
    });
});

describe('hasAnyKey', function (): void {
    it('returns true when workspace has a workspace-level key', function (): void {
        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->anthropic()
            ->create();

        expect($this->resolver->hasAnyKey($this->workspace))->toBeTrue();
    });

    it('returns false when no workspace-level keys exist', function (): void {
        expect($this->resolver->hasAnyKey($this->workspace))->toBeFalse();
    });

    it('returns false when only repo-level keys exist', function (): void {
        ProviderKey::factory()
            ->anthropic()
            ->create(['workspace_id' => $this->workspace->id]);

        expect($this->resolver->hasAnyKey($this->workspace))->toBeFalse();
    });

    it('returns true with multiple providers', function (): void {
        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->openai()
            ->create();

        expect($this->resolver->hasAnyKey($this->workspace))->toBeTrue();
    });
});

describe('resolveConfiguration', function (): void {
    it('resolves BYOK with Anthropic key and model from database', function (): void {
        AiOption::factory()->anthropicSonnet45()->create();

        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->anthropic()
            ->create();

        $config = $this->resolver->resolveConfiguration($this->workspace);

        expect($config)->toBeInstanceOf(BriefingAiConfiguration::class)
            ->and($config->provider)->toBe(AiProvider::Anthropic)
            ->and($config->model)->toBe('claude-sonnet-4-5-20250929')
            ->and($config->apiKey)->toStartWith('sk-ant-test-')
            ->and($config->isByok)->toBeTrue()
            ->and($config->usesPlatformKey())->toBeFalse();
    });

    it('resolves BYOK with OpenAI key and model from database', function (): void {
        AiOption::factory()->openaiGpt4o()->create();

        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->openai()
            ->create();

        $config = $this->resolver->resolveConfiguration($this->workspace);

        expect($config->provider)->toBe(AiProvider::OpenAI)
            ->and($config->model)->toBe('gpt-4o')
            ->and($config->apiKey)->not()->toBeNull()
            ->and($config->isByok)->toBeTrue();
    });

    it('prefers Anthropic when both keys exist', function (): void {
        AiOption::factory()->anthropicSonnet45()->create();

        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->anthropic()
            ->create();

        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->openai()
            ->create();

        $config = $this->resolver->resolveConfiguration($this->workspace);

        expect($config->provider)->toBe(AiProvider::Anthropic)
            ->and($config->isByok)->toBeTrue();
    });

    it('uses hardcoded fallback model when no database default exists', function (): void {
        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->anthropic()
            ->create();

        $config = $this->resolver->resolveConfiguration($this->workspace);

        expect($config->provider)->toBe(AiProvider::Anthropic)
            ->and($config->model)->toBe('claude-sonnet-4-5-20250929')
            ->and($config->isByok)->toBeTrue();
    });

    it('resolves platform config when no BYOK keys exist', function (): void {
        config()->set('briefings.platform.provider', 'anthropic');
        AiOption::factory()->anthropicSonnet45()->create();

        $config = $this->resolver->resolveConfiguration($this->workspace);

        expect($config->provider)->toBe(AiProvider::Anthropic)
            ->and($config->model)->toBe('claude-sonnet-4-5-20250929')
            ->and($config->apiKey)->toBeNull()
            ->and($config->isByok)->toBeFalse()
            ->and($config->usesPlatformKey())->toBeTrue();
    });

    it('resolves platform OpenAI config when configured', function (): void {
        config()->set('briefings.platform.provider', 'openai');
        AiOption::factory()->openaiGpt4o()->create();

        $config = $this->resolver->resolveConfiguration($this->workspace);

        expect($config->provider)->toBe(AiProvider::OpenAI)
            ->and($config->model)->toBe('gpt-4o')
            ->and($config->apiKey)->toBeNull()
            ->and($config->isByok)->toBeFalse();
    });

    it('throws when platform provider config is invalid', function (): void {
        config()->set('briefings.platform.provider', 'invalid-provider');

        $this->resolver->resolveConfiguration($this->workspace);
    })->throws(RuntimeException::class, 'Invalid briefings platform provider: invalid-provider');
});
