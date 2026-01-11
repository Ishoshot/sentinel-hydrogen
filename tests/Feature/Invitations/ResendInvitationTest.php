<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvitationSentNotification;
use Illuminate\Support\Facades\Notification;

it('can resend invitation as owner', function (): void {
    Notification::fake();

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

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.resend', [$workspace, $invitation]));

    $response->assertOk()
        ->assertJsonPath('message', 'Invitation resent successfully.');

    Notification::assertSentOnDemand(InvitationSentNotification::class);
});

it('can resend invitation as admin', function (): void {
    Notification::fake();

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
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
        'email' => 'invitee@example.com',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(route('invitations.resend', [$workspace, $invitation]));

    $response->assertOk();
});

it('cannot resend invitation as member', function (): void {
    Notification::fake();

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
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->postJson(route('invitations.resend', [$workspace, $invitation]));

    $response->assertForbidden();

    Notification::assertNothingSent();
});

it('cannot resend accepted invitation', function (): void {
    Notification::fake();

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
        'accepted_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.resend', [$workspace, $invitation]));

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot resend an accepted invitation.');

    Notification::assertNothingSent();
});

it('cannot resend expired invitation', function (): void {
    Notification::fake();

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
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.resend', [$workspace, $invitation]));

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot resend an expired invitation.');

    Notification::assertNothingSent();
});

it('cannot resend invitation from another workspace', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace1 = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace2 = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace1->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace1->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $workspace2->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace2->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace2->id,
        'team_id' => $workspace2->team->id,
        'invited_by_id' => $owner->id,
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.resend', [$workspace1, $invitation]));

    $response->assertNotFound();

    Notification::assertNothingSent();
});

it('sends notification to existing user when resending', function (): void {
    Notification::fake();

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

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.resend', [$workspace, $invitation]));

    $response->assertOk();

    Notification::assertSentTo($invitee, InvitationSentNotification::class);
});
