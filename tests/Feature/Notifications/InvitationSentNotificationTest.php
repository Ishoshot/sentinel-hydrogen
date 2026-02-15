<?php

declare(strict_types=1);

use App\Enums\Workspace\TeamRole;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvitationSentNotification;
use Illuminate\Support\Facades\Notification;

it('sends invitation notification via mail and database channels', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    $user->notify(new InvitationSentNotification($invitation));

    Notification::assertSentTo($user, InvitationSentNotification::class, function ($notification, $channels): bool {
        return in_array('mail', $channels, true) && in_array('database', $channels, true);
    });
});

it('renders mail with workspace name in subject', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Acme Engineering']);
    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    $notification = new InvitationSentNotification($invitation);
    $mailMessage = $notification->toMail($invitation);

    expect($mailMessage->subject)->toBe("You're invited to join Acme Engineering");
});

it('uses fallback workspace name when workspace is null', function (): void {
    $workspace = Workspace::factory()->create();
    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    // Force workspace to be null to test fallback
    $invitation->setRelation('workspace', null);

    $notification = new InvitationSentNotification($invitation);
    $mailMessage = $notification->toMail($invitation);

    expect($mailMessage->subject)->toBe("You're invited to join a workspace");
});

it('renders mail with correct markdown template', function (): void {
    $workspace = Workspace::factory()->create();
    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    $notification = new InvitationSentNotification($invitation);
    $mailMessage = $notification->toMail($invitation);

    expect($mailMessage->markdown)->toBe('emails.invitation-sent');
});

it('includes accept url with invitation token in mail', function (): void {
    config()->set('app.frontend_url', 'https://app.sentinel.dev');

    $workspace = Workspace::factory()->create();
    $invitation = Invitation::factory()->forWorkspace($workspace)->create(['token' => 'test-token-abc123']);

    $notification = new InvitationSentNotification($invitation);
    $mailMessage = $notification->toMail($invitation);

    expect($mailMessage->viewData['acceptUrl'])->toBe('https://app.sentinel.dev/invitations/test-token-abc123');
});

it('returns correct array representation for database storage', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Dev Team']);
    $inviter = User::factory()->create(['name' => 'Jane Admin']);
    $invitation = Invitation::factory()
        ->forWorkspace($workspace)
        ->invitedBy($inviter)
        ->create(['role' => TeamRole::Member]);

    $notification = new InvitationSentNotification($invitation);
    $array = $notification->toArray($invitation);

    expect($array)->toHaveKeys(['invitation_id', 'workspace_id', 'workspace_name', 'invited_by_name', 'role'])
        ->and($array['invitation_id'])->toBe($invitation->id)
        ->and($array['workspace_id'])->toBe($workspace->id)
        ->and($array['workspace_name'])->toBe('Dev Team')
        ->and($array['invited_by_name'])->toBe('Jane Admin')
        ->and($array['role'])->toBe('member');
});

it('returns correct database type', function (): void {
    $workspace = Workspace::factory()->create();
    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    $notification = new InvitationSentNotification($invitation);

    expect($notification->databaseType($invitation))->toBe('invitation.sent');
});

it('queues on the notifications queue', function (): void {
    $workspace = Workspace::factory()->create();
    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    $notification = new InvitationSentNotification($invitation);

    expect($notification->queue)->toBe('notifications');
});

it('includes invitation in mail view data', function (): void {
    $workspace = Workspace::factory()->create();
    $invitation = Invitation::factory()->forWorkspace($workspace)->create();

    $notification = new InvitationSentNotification($invitation);
    $mailMessage = $notification->toMail($invitation);

    expect($mailMessage->viewData['invitation'])->toBe($invitation);
});

it('returns array with admin role value', function (): void {
    $workspace = Workspace::factory()->create();
    $invitation = Invitation::factory()->forWorkspace($workspace)->asAdmin()->create();

    $notification = new InvitationSentNotification($invitation);
    $array = $notification->toArray($invitation);

    expect($array['role'])->toBe('admin');
});
