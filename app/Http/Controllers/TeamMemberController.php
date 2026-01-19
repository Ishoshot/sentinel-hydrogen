<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Teams\RemoveTeamMember;
use App\Actions\Teams\UpdateTeamMemberRole;
use App\Enums\TeamRole;
use App\Http\Requests\TeamMember\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\TeamMember;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class TeamMemberController
{
    /**
     * List all members of the workspace.
     */
    public function index(Workspace $workspace): JsonResponse
    {
        $members = $workspace->teamMembers()
            ->with('user')
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END")
            ->orderBy('joined_at')
            ->get();

        return response()->json([
            'data' => TeamMemberResource::collection($members),
        ]);
    }

    /**
     * Update a team member's role.
     */
    public function update(
        UpdateTeamMemberRequest $request,
        Workspace $workspace,
        TeamMember $member,
        UpdateTeamMemberRole $updateTeamMemberRole,
    ): JsonResponse {
        if ($member->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('update', $member);

        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        /** @var \App\Models\User $user */

        /** @var string $role */
        $role = $request->validated('role');

        try {
            $updateTeamMemberRole->handle(
                member: $member,
                role: TeamRole::from($role),
                actor: $user,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            return response()->json([
                'message' => $invalidArgumentException->getMessage(),
            ], 422);
        }

        $member->refresh();
        $member->load('user');

        return response()->json([
            'data' => new TeamMemberResource($member),
            'message' => 'Member role updated successfully.',
        ]);
    }

    /**
     * Remove a team member from the workspace.
     */
    public function destroy(
        Request $request,
        Workspace $workspace,
        TeamMember $member,
        RemoveTeamMember $removeTeamMember,
    ): JsonResponse {
        if ($member->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('delete', $member);

        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        /** @var \App\Models\User $user */
        try {
            $removeTeamMember->handle($member, $user);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return response()->json([
                'message' => $invalidArgumentException->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Member removed successfully.',
        ]);
    }
}
