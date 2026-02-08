<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Exceptions\NoProviderKeyException;
use App\Models\CommandRun;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\Commands\CommandAgentService;
use App\Services\Reviews\Contracts\ProviderKeyResolver;

it('throws NoProviderKeyException when decrypted provider key is empty', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $commandRun = CommandRun::factory()->forRepository($repository)->create();

    // Create a real ProviderKey with an empty encrypted_key
    $providerKey = ProviderKey::factory()->forRepository($repository)->create([
        'provider' => AiProvider::Anthropic,
        'encrypted_key' => '',
    ]);

    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $keyResolver->shouldReceive('getProviderKey')
        ->andReturn($providerKey);

    app()->instance(ProviderKeyResolver::class, $keyResolver);

    $service = app(CommandAgentService::class);
    $service->execute($commandRun);
})->throws(NoProviderKeyException::class);
