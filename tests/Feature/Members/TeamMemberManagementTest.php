<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

it('can list workspace members', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('members.index', $workspace));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $user->id)
        ->assertJsonPath('data.0.role', 'owner');
});

it('can update member role as owner', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $membership = $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson(route('members.update', [$workspace, $membership]), [
            'role' => 'admin',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.role', 'admin');
});

it('can update member role as admin', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
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
    $membership = $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson(route('members.update', [$workspace, $membership]), [
            'role' => 'admin',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.role', 'admin');
});

it('cannot update member role as regular member', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherMember = User::factory()->create();
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
    $otherMembership = $workspace->teamMembers()->create([
        'user_id' => $otherMember->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->patchJson(route('members.update', [$workspace, $otherMembership]), [
            'role' => 'admin',
        ]);

    $response->assertForbidden();
});

it('cannot change owner role', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $ownerMembership = $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson(route('members.update', [$workspace, $ownerMembership]), [
            'role' => 'admin',
        ]);

    $response->assertForbidden();
});

it('can remove member as owner', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $membership = $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->deleteJson(route('members.destroy', [$workspace, $membership]));

    $response->assertOk()
        ->assertJsonPath('message', 'Member removed successfully.');

    expect($workspace->teamMembers()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('cannot remove owner', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $ownerMembership = $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->deleteJson(route('members.destroy', [$workspace, $ownerMembership]));

    $response->assertForbidden();
});

it('cannot remove member as regular member', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherMember = User::factory()->create();
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
    $otherMembership = $workspace->teamMembers()->create([
        'user_id' => $otherMember->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->deleteJson(route('members.destroy', [$workspace, $otherMembership]));

    $response->assertForbidden();
});

it('requires valid role when updating member', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $membership = $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson(route('members.update', [$workspace, $membership]), [
            'role' => 'invalid-role',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});
