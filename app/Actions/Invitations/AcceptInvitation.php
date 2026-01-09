<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AcceptInvitation
{
    /**
     * Accept an invitation and create the team membership.
     *
     * @throws InvalidArgumentException
     */
    public function execute(Invitation $invitation, User $user): TeamMember
    {
        if ($invitation->isExpired()) {
            throw new InvalidArgumentException('This invitation has expired.');
        }

        if ($invitation->isAccepted()) {
            throw new InvalidArgumentException('This invitation has already been accepted.');
        }

        $workspace = $invitation->workspace;

        if ($workspace === null) {
            throw new InvalidArgumentException('The workspace for this invitation no longer exists.');
        }

        $existingMember = $workspace->teamMembers()
            ->where('user_id', $user->id)
            ->exists();

        if ($existingMember) {
            throw new InvalidArgumentException('You are already a member of this workspace.');
        }

        return DB::transaction(function () use ($invitation, $user): TeamMember {
            $member = TeamMember::create([
                'user_id' => $user->id,
                'team_id' => $invitation->team_id,
                'workspace_id' => $invitation->workspace_id,
                'role' => $invitation->role,
                'joined_at' => now(),
            ]);

            $invitation->markAsAccepted();

            return $member;
        });
    }
}
