<?php

declare(strict_types=1);

use App\Actions\Invitations\CancelInvitation;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('cancels a pending invitation', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
    ]);

    $action = new CancelInvitation;
    $action->handle($invitation);

    $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
});

it('throws exception when cancelling an accepted invitation', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $invitation = Invitation::factory()->accepted()->create([
        'workspace_id' => $workspace->id,
        'team_id' => $workspace->team->id,
        'invited_by_id' => $owner->id,
    ]);

    $action = new CancelInvitation;
    $action->handle($invitation);
})->throws(InvalidArgumentException::class, 'Cannot cancel an accepted invitation.');
