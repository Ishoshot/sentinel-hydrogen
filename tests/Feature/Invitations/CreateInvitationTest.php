<?php

declare(strict_types=1);

use App\Enums\Billing\SubscriptionStatus;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvitationSentNotification;
use Illuminate\Support\Facades\Notification;

it('can create an invitation as owner', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'invited@example.com',
            'role' => 'member',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'invited@example.com')
        ->assertJsonPath('data.role', 'member')
        ->assertJsonPath('message', 'Invitation sent successfully.');
});

it('can create an invitation as admin', function (): void {
    $plan = Plan::factory()->create([
        'team_size_limit' => 10,
    ]);
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);
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
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'invited@example.com',
            'role' => 'member',
        ]);

    $response->assertCreated();
});

it('cannot create an invitation as member', function (): void {
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
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'invited@example.com',
            'role' => 'member',
        ]);

    $response->assertForbidden();
});

it('generates a token for invitation', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'invited@example.com',
            'role' => 'member',
        ]);

    $invitation = Invitation::where('email', 'invited@example.com')->first();
    expect($invitation->token)->not->toBeEmpty()
        ->and(mb_strlen($invitation->token))->toBeGreaterThan(30);
});

it('sets expiration date for invitation', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'invited@example.com',
            'role' => 'member',
        ]);

    $invitation = Invitation::where('email', 'invited@example.com')->first();
    expect($invitation->expires_at)->not->toBeNull()
        ->and($invitation->expires_at->isFuture())->toBeTrue();
});

it('cannot invite existing workspace member', function (): void {
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

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'existing@example.com',
            'role' => 'admin',
        ]);

    $response->assertUnprocessable();
});

it('requires email to create invitation', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'role' => 'member',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('requires valid email format', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'not-an-email',
            'role' => 'member',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('requires role to create invitation', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'invited@example.com',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('cannot invite with owner role', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'invited@example.com',
            'role' => 'owner',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('can list pending invitations', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'pending@example.com',
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(route('invitations.index', $workspace));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'pending@example.com');
});

it('can cancel invitation', function (): void {
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
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->deleteJson(route('invitations.destroy', [$workspace, $invitation]));

    $response->assertOk()
        ->assertJsonPath('message', 'Invitation cancelled.');

    expect(Invitation::find($invitation->id))->toBeNull();
});

it('sends notification when invitation is created', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'newuser@example.com',
            'role' => 'member',
        ]);

    Notification::assertSentOnDemand(InvitationSentNotification::class);
});

it('sends db notification to existing user when invited', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'existing@example.com',
            'role' => 'member',
        ]);

    Notification::assertSentTo($existingUser, InvitationSentNotification::class);
});
