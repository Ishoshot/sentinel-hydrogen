<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Briefings\CreateBriefing;
use App\Actions\Admin\Briefings\DeleteBriefing;
use App\Actions\Admin\Briefings\ListBriefings;
use App\Actions\Admin\Briefings\UpdateBriefing;
use App\Http\Requests\Admin\Briefing\IndexBriefingsRequest;
use App\Http\Requests\Admin\Briefing\StoreBriefingRequest;
use App\Http\Requests\Admin\Briefing\UpdateBriefingRequest;
use App\Http\Resources\Admin\BriefingResource;
use App\Models\Briefing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin controller for managing briefing templates.
 */
final class BriefingController
{
    /**
     * List all briefing templates.
     */
    public function index(IndexBriefingsRequest $request, ListBriefings $listBriefings): AnonymousResourceCollection
    {
        $briefings = $listBriefings->handle(
            activeOnly: $request->activeOnly(),
            systemOnly: $request->systemOnly(),
            perPage: $request->perPage(),
        );

        return BriefingResource::collection($briefings);
    }

    /**
     * Store a new briefing template.
     */
    public function store(
        StoreBriefingRequest $request,
        CreateBriefing $createBriefing,
    ): JsonResponse {
        $briefing = $createBriefing->handle($request->validated());

        return response()->json([
            'data' => new BriefingResource($briefing),
            'message' => 'Briefing created successfully.',
        ], 201);
    }

    /**
     * Show a specific briefing template.
     */
    public function show(Briefing $briefing): JsonResponse
    {
        $briefing->loadCount(['generations', 'subscriptions']);

        return response()->json([
            'data' => new BriefingResource($briefing),
        ]);
    }

    /**
     * Update a briefing template.
     */
    public function update(
        UpdateBriefingRequest $request,
        Briefing $briefing,
        UpdateBriefing $updateBriefing,
    ): JsonResponse {
        $briefing = $updateBriefing->handle($briefing, $request->validated());

        return response()->json([
            'data' => new BriefingResource($briefing),
            'message' => 'Briefing updated successfully.',
        ]);
    }

    /**
     * Delete a briefing template.
     */
    public function destroy(
        Briefing $briefing,
        DeleteBriefing $deleteBriefing,
    ): JsonResponse {
        $deleteBriefing->handle($briefing);

        return response()->json([
            'message' => 'Briefing deleted successfully.',
        ]);
    }
}
