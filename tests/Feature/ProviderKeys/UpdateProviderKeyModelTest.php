<?php

declare(strict_types=1);

use App\Actions\ProviderKeys\UpdateProviderKeyModel;
use App\Models\AiOption;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\Workspace;

it('updates provider key model selection', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $providerKey = ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'provider' => 'anthropic',
    ]);
    $aiOption = AiOption::factory()->create([
        'provider' => 'anthropic',
        'is_active' => true,
    ]);

    $action = new UpdateProviderKeyModel;
    $result = $action->handle($providerKey, $aiOption->id);

    expect($result->provider_model_id)->toBe($aiOption->id);
});

it('clears model selection when null', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $aiOption = AiOption::factory()->create([
        'provider' => 'anthropic',
        'is_active' => true,
    ]);
    $providerKey = ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'provider' => 'anthropic',
        'provider_model_id' => $aiOption->id,
    ]);

    $action = new UpdateProviderKeyModel;
    $result = $action->handle($providerKey, null);

    expect($result->provider_model_id)->toBeNull();
});

it('throws exception for invalid model', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $providerKey = ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'provider' => 'anthropic',
    ]);

    $action = new UpdateProviderKeyModel;
    $action->handle($providerKey, 99999);
})->throws(InvalidArgumentException::class);
