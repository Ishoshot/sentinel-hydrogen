<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;

it('blocks provider key storage when byok is disabled', function (): void {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'features' => ['byok_enabled' => false],
    ]);

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'openai',
            'key' => 'sk-test-key',
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Bring Your Own Key is not available on your current plan.');
});
