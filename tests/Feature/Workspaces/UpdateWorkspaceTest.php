<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

it('can update workspace name', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson(route('workspaces.update', $workspace), [
            'name' => 'Updated Name',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('message', 'Workspace updated successfully.');
});

it('also updates team name when workspace name changes', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson(route('workspaces.update', $workspace), [
            'name' => 'New Name',
        ]);

    $workspace->refresh();
    expect($workspace->team->name)->toBe('New Name');
});

it('requires name to update workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson(route('workspaces.update', $workspace), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('forbids non-owners from updating workspace', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->patchJson(route('workspaces.update', $workspace), [
            'name' => 'Hacked Name',
        ]);

    $response->assertForbidden();
});

it('allows admins to update workspace', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $workspace->teamMembers()->create([
        'user_id' => $admin->id,
        'team_id' => $workspace->team->id,
        'role' => 'admin',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson(route('workspaces.update', $workspace), [
            'name' => 'Admin Updated',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Admin Updated');
});

it('can delete workspace as owner', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson(route('workspaces.destroy', $workspace));

    $response->assertOk()
        ->assertJsonPath('message', 'Workspace deleted successfully.');

    expect(Workspace::find($workspace->id))->toBeNull();
});

it('forbids non-owners from deleting workspace', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $workspace->teamMembers()->create([
        'user_id' => $admin->id,
        'team_id' => $workspace->team->id,
        'role' => 'admin',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson(route('workspaces.destroy', $workspace));

    $response->assertForbidden();
});

it('requires authentication to update workspace', function (): void {
    $workspace = Workspace::factory()->create();

    $response = $this->patchJson(route('workspaces.update', $workspace), [
        'name' => 'Updated',
    ]);

    $response->assertUnauthorized();
});
