<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;

it('can accept invitation as authenticated user', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'invitee@example.com',
        'role' => 'member',
    ]);

    $response = $this->actingAs($invitee, 'sanctum')
        ->postJson(route('invitations.accept', $invitation->token));

    $response->assertOk()
        ->assertJsonStructure(['message', 'data']);

    expect($workspace->teamMembers()->where('user_id', $invitee->id)->exists())->toBeTrue();
});

it('creates membership with correct role', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'invitee@example.com',
        'role' => 'admin',
    ]);

    $this->actingAs($invitee, 'sanctum')
        ->postJson(route('invitations.accept', $invitation->token));

    $membership = $workspace->teamMembers()->where('user_id', $invitee->id)->first();
    expect($membership->role->value)->toBe('admin');
});

it('marks invitation as accepted', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'invitee@example.com',
    ]);

    $this->actingAs($invitee, 'sanctum')
        ->postJson(route('invitations.accept', $invitation->token));

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull()
        ->and($invitation->isAccepted())->toBeTrue();
});

it('returns 404 for invalid token', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('invitations.accept', 'invalid-token'));

    $response->assertNotFound();
});

it('returns 410 for expired invitation', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $invitation = Invitation::factory()->expired()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'invitee@example.com',
    ]);

    $response = $this->actingAs($invitee, 'sanctum')
        ->postJson(route('invitations.accept', $invitation->token));

    $response->assertStatus(410);
});

it('returns 409 for already accepted invitation', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $invitation = Invitation::factory()->accepted()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'invitee@example.com',
    ]);

    $response = $this->actingAs($invitee, 'sanctum')
        ->postJson(route('invitations.accept', $invitation->token));

    $response->assertStatus(409);
});

it('returns 401 for unauthenticated request', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'invitee@example.com',
    ]);

    $response = $this->postJson(route('invitations.accept', $invitation->token));

    $response->assertUnauthorized()
        ->assertJsonStructure(['message', 'invitation']);
});

it('returns invitation info for unauthenticated request', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Awesome Workspace',
    ]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'invitee@example.com',
        'role' => 'admin',
    ]);

    $response = $this->postJson(route('invitations.accept', $invitation->token));

    $response->assertUnauthorized()
        ->assertJsonPath('invitation.workspace_name', 'Awesome Workspace')
        ->assertJsonPath('invitation.role', 'admin')
        ->assertJsonPath('invitation.email', 'invitee@example.com');
});

it('prevents accepting invitation for user already in workspace', function (): void {
    $owner = User::factory()->create();
    $existingMember = User::factory()->create(['email' => 'existing@example.com']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $workspace->teamMembers()->create([
        'user_id' => $existingMember->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'existing@example.com',
    ]);

    $response = $this->actingAs($existingMember, 'sanctum')
        ->postJson(route('invitations.accept', $invitation->token));

    $response->assertUnprocessable();
});

it('allows any authenticated user to accept invitation regardless of email match', function (): void {
    $owner = User::factory()->create();
    $anyUser = User::factory()->create(['email' => 'different@example.com']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'original-invitee@example.com',
    ]);

    $response = $this->actingAs($anyUser, 'sanctum')
        ->postJson(route('invitations.accept', $invitation->token));

    $response->assertOk();
    expect($workspace->teamMembers()->where('user_id', $anyUser->id)->exists())->toBeTrue();
});
