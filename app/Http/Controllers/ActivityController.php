<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ActivityController
{
    /**
     * List activities for a workspace.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $query = Activity::with('actor')
            ->where('workspace_id', $workspace->id)
            ->orderBy('created_at', 'desc');

        // Filter by type if provided
        $type = $request->query('type');
        if (is_string($type) && in_array($type, ActivityType::values(), true)) {
            $query->where('type', $type);
        }

        // Filter by category if provided
        $category = $request->query('category');
        if (is_string($category)) {
            $categoryTypes = collect(ActivityType::cases())
                ->filter(fn (ActivityType $activityType): bool => $activityType->category() === $category)
                ->map(fn (ActivityType $activityType) => $activityType->value)
                ->values()
                ->all();

            if ($categoryTypes !== []) {
                $query->whereIn('type', $categoryTypes);
            }
        }

        $perPage = min((int) $request->query('per_page', '20'), 100);
        $activities = $query->paginate($perPage);

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
