<?php

declare(strict_types=1);

use App\Enums\ProviderType;
use App\Enums\Queue as QueueEnum;
use App\Jobs\Usage\AggregateUsage;
use App\Models\Annotation;
use App\Models\Connection;
use App\Models\Finding;
use App\Models\Installation;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

it('returns current period usage for a workspace', function (): void {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $plan->id,
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
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

    $run = Run::factory()->forRepository($repository)->create([
        'workspace_id' => $workspace->id,
        'created_at' => now(),
    ]);

    $finding = Finding::factory()->forRun($run)->create([
        'workspace_id' => $workspace->id,
        'created_at' => now(),
    ]);

    Annotation::factory()->forFinding($finding)->forProvider($provider)->create([
        'workspace_id' => $workspace->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('usage.show', $workspace));

    $response->assertOk()
        ->assertJsonPath('data.runs_count', 1)
        ->assertJsonPath('data.findings_count', 1)
        ->assertJsonPath('data.annotations_count', 1);
});

it('dispatches usage aggregation job via command', function (): void {
    Queue::fake();

    Artisan::call('usage:aggregate');

    Queue::assertPushedOn(QueueEnum::System->value, AggregateUsage::class);
});

it('schedules usage aggregation hourly', function (): void {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('usage:aggregate');
});
