<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Activities\ListWorkspaceActivities;
use App\Http\Requests\Activity\IndexActivitiesRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

final class ActivityController
{
    /**
     * List activities for a workspace.
     */
    public function index(
        IndexActivitiesRequest $request,
        Workspace $workspace,
        ListWorkspaceActivities $listActivities,
    ): JsonResponse {
        $activities = $listActivities->handle(
            workspace: $workspace,
            type: $request->type(),
            category: $request->category(),
            perPage: $request->perPage(),
        );

        return response()->json([
            'data' => ActivityResource::collection($activities->items()),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
            'links' => [
                'first' => $activities->url(1),
                'last' => $activities->url($activities->lastPage()),
                'prev' => $activities->previousPageUrl(),
                'next' => $activities->nextPageUrl(),
            ],
        ]);
    }
}
