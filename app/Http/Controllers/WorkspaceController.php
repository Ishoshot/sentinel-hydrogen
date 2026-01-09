<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Requests\Workspace\CreateWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class WorkspaceController
{
    /**
     * List all workspaces the user belongs to.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $workspaces = Workspace::query()
            ->forUser($user)
            ->with(['team', 'owner'])
            ->withCount('teamMembers')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => WorkspaceResource::collection($workspaces),
        ]);
    }

    /**
     * Create a new workspace.
     */
    public function store(
        CreateWorkspaceRequest $request,
        CreateWorkspace $createWorkspace,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        /** @var string $name */
        $name = $request->validated('name');

        $workspace = $createWorkspace->execute(
            owner: $user,
            name: $name,
        );

        $workspace->load(['team', 'owner']);
        $workspace->loadCount('teamMembers');

        return response()->json([
            'data' => new WorkspaceResource($workspace),
            'message' => 'Workspace created successfully.',
        ], 201);
    }

    /**
     * Get a specific workspace.
     */
    public function show(Workspace $workspace): JsonResponse
    {
        $workspace->load(['team.members.user', 'owner']);
        $workspace->loadCount('teamMembers');

        return response()->json([
            'data' => new WorkspaceResource($workspace),
        ]);
    }

    /**
     * Update a workspace.
     */
    public function update(
        UpdateWorkspaceRequest $request,
        Workspace $workspace,
        UpdateWorkspace $updateWorkspace,
    ): JsonResponse {
        Gate::authorize('update', $workspace);

        /** @var string $name */
        $name = $request->validated('name');

        $updateWorkspace->execute(
            workspace: $workspace,
            name: $name,
        );

        $workspace->refresh();
        $workspace->load(['team', 'owner']);
        $workspace->loadCount('teamMembers');

        return response()->json([
            'data' => new WorkspaceResource($workspace),
            'message' => 'Workspace updated successfully.',
        ]);
    }

    /**
     * Delete a workspace.
     */
    public function destroy(
        Request $request,
        Workspace $workspace,
        DeleteWorkspace $deleteWorkspace,
    ): JsonResponse {
        Gate::authorize('delete', $workspace);

        $deleteWorkspace->execute($workspace);

        return response()->json([
            'message' => 'Workspace deleted successfully.',
        ]);
    }

    /**
     * Switch to a different workspace.
     */
    public function switch(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->belongsToWorkspace($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $workspace->load(['team', 'owner']);
        $workspace->loadCount('teamMembers');

        return response()->json([
            'data' => new WorkspaceResource($workspace),
            'message' => 'Switched to '.$workspace->name,
        ]);
    }
}
