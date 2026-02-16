<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Invitations\CancelInvitation;
use App\Actions\Invitations\CreateInvitation;
use App\Actions\Invitations\ResendInvitation;
use App\Enums\Workspace\TeamRole;
use App\Http\Requests\Invitation\CreateInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class InvitationController
{
    /**
     * List pending invitations for a workspace.
     */
    public function index(Workspace $workspace): JsonResponse
    {
        $invitations = $workspace->invitations()
            ->with('invitedBy')
            ->pending()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => InvitationResource::collection($invitations),
        ]);
    }

    /**
     * Create a new invitation.
     */
    public function store(
        CreateInvitationRequest $request,
        Workspace $workspace,
        CreateInvitation $createInvitation,
    ): JsonResponse {
        Gate::authorize('create', [Invitation::class, $workspace]);

        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        /** @var \App\Models\User $user */

        /** @var string $email */
        $email = $request->validated('email');

        /** @var string $role */
        $role = $request->validated('role');

        $invitation = $createInvitation->handle(
            workspace: $workspace,
            invitedBy: $user,
            email: $email,
            role: TeamRole::from($role),
        );

        $invitation->load('invitedBy');

        return response()->json([
            'data' => new InvitationResource($invitation),
            'message' => 'Invitation sent successfully.',
        ], 201);
    }

    /**
     * Cancel a pending invitation.
     */
    public function destroy(
        Workspace $workspace,
        Invitation $invitation,
        CancelInvitation $cancelInvitation,
    ): JsonResponse {
        if ($invitation->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('delete', $invitation);

        $cancelInvitation->handle($invitation);

        return response()->json([
            'message' => 'Invitation cancelled.',
        ]);
    }

    /**
     * Resend an invitation notification.
     */
    public function resend(
        Workspace $workspace,
        Invitation $invitation,
        ResendInvitation $resendInvitation,
    ): JsonResponse {
        if ($invitation->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('create', [Invitation::class, $workspace]);

        $resendInvitation->handle($invitation);

        return response()->json([
            'message' => 'Invitation resent successfully.',
        ]);
    }

    /**
     * Accept an invitation using its token.
     */
    public function accept(
        Request $request,
        string $token,
        AcceptInvitation $acceptInvitation,
    ): JsonResponse {
        $invitation = Invitation::where('token', $token)->first();

        if ($invitation === null) {
            return response()->json([
                'message' => 'Invalid invitation link.',
            ], 404);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'This invitation has expired.',
            ], 410);
        }

        if ($invitation->isAccepted()) {
            return response()->json([
                'message' => 'This invitation has already been accepted.',
            ], 409);
        }

        $user = $request->user();
        $workspace = $invitation->workspace;

        if ($workspace === null) {
            return response()->json([
                'message' => 'The workspace for this invitation no longer exists.',
            ], 404);
        }

        if ($user === null) {
            return response()->json([
                'message' => 'Authentication required to accept this invitation.',
                'invitation' => [
                    'workspace_name' => $workspace->name,
                    'role' => $invitation->role->value,
                    'email' => $invitation->email,
                ],
            ], 401);
        }

        /** @var \App\Models\User $user */
        $acceptInvitation->handle($invitation, $user);

        $invitation->load(['workspace.team', 'workspace.owner']);
        $workspace->loadCount('teamMembers');

        return response()->json([
            'message' => 'You have joined '.$workspace->name,
            'data' => new InvitationResource($invitation),
        ]);
    }
}
