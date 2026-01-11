<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

it('can switch to a workspace user belongs to', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.switch', $workspace));

    $response->assertOk()
        ->assertJsonPath('data.id', $workspace->id)
        ->assertJsonStructure(['data', 'message']);
});

it('returns workspace data when switching', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Test Workspace',
    ]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.switch', $workspace));

    $response->assertOk()
        ->assertJsonPath('data.name', 'Test Workspace')
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug'],
        ]);
});

it('cannot switch to workspace user does not belong to', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $otherUser->id]);
    $workspace->teamMembers()->create([
        'user_id' => $otherUser->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.switch', $workspace));

    $response->assertForbidden();
});

it('can view workspace user belongs to', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('workspaces.show', $workspace));

    $response->assertOk()
        ->assertJsonPath('data.id', $workspace->id);
});

it('cannot view workspace user does not belong to', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $otherUser->id]);
    $workspace->teamMembers()->create([
        'user_id' => $otherUser->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('workspaces.show', $workspace));

    $response->assertForbidden();
});

it('requires authentication to switch workspace', function (): void {
    $workspace = Workspace::factory()->create();

    $response = $this->postJson(route('workspaces.switch', $workspace));

    $response->assertUnauthorized();
});
