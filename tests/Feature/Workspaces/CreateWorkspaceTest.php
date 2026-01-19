<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

it('can create a workspace', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), [
            'name' => 'My New Workspace',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'My New Workspace')
        ->assertJsonPath('message', 'Workspace created successfully.');
});

it('creates a team with same name as workspace', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), [
            'name' => 'Awesome Project',
        ]);

    $workspace = Workspace::where('name', 'Awesome Project')->first();

    expect($workspace->team->name)->toBe('Awesome Project');
});

it('adds creator as owner of new workspace', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), [
            'name' => 'My Workspace',
        ]);

    $workspaceId = $response->json('data.id');
    $workspace = Workspace::find($workspaceId);

    expect($workspace->owner_id)->toBe($user->id);

    $membership = $workspace->teamMembers()->where('user_id', $user->id)->first();
    expect($membership->role->value)->toBe('owner');
});

it('generates a unique slug for workspace', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), [
            'name' => 'My Workspace',
        ]);

    $response->assertJsonStructure(['data' => ['slug']]);
    expect($response->json('data.slug'))->not->toBeEmpty();
});

it('requires name to create workspace', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('requires name to be a string', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), [
            'name' => 123,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('requires name to be at most 255 characters', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), [
            'name' => str_repeat('a', 256),
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('requires authentication to create workspace', function (): void {
    $response = $this->postJson(route('workspaces.store'), [
        'name' => 'My Workspace',
    ]);

    $response->assertUnauthorized();
});

it('can list user workspaces', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('workspaces.index'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $workspace->id);
});

it('only lists workspaces user belongs to', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $userWorkspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $userWorkspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $otherWorkspace = Workspace::factory()->create(['owner_id' => $otherUser->id]);
    $otherWorkspace->teamMembers()->create([
        'user_id' => $otherUser->id,
        'team_id' => $otherWorkspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('workspaces.index'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $userWorkspace->id);
});

it('blocks additional workspace creation when existing workspace is on free plan', function (): void {
    $foundationPlan = Plan::factory()->create(); // Foundation is free

    $user = User::factory()->create();
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), [
            'name' => 'Second Workspace',
        ]);

    $response->assertForbidden()
        ->assertJsonPath('error', 'paid_plan_required');
});

it('allows additional workspace creation when all existing workspaces are on paid plans', function (): void {
    $illuminatePlan = Plan::factory()->illuminate()->create();

    $user = User::factory()->create();
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), [
            'name' => 'Second Workspace',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Second Workspace');
});

it('blocks workspace creation when any existing workspace is on free plan', function (): void {
    $foundationPlan = Plan::factory()->create(); // Foundation is free
    $illuminatePlan = Plan::factory()->illuminate()->create();

    $user = User::factory()->create();

    // Create workspace with paid plan
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    // Create workspace with free plan
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    // Should be blocked because one workspace is on free plan
    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), [
            'name' => 'Third Workspace',
        ]);

    $response->assertForbidden()
        ->assertJsonPath('error', 'paid_plan_required');
});
