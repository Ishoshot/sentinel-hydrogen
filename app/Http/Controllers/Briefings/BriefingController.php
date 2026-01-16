<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Http\Resources\Briefings\BriefingResource;
use App\Models\Briefing;
use App\Models\Workspace;
use App\Services\Briefings\BriefingLimitEnforcer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final readonly class BriefingController
{
    /**
     * Create a new controller instance.
     *
     * @param  BriefingLimitEnforcer  $limitEnforcer  Service to check plan limits
     */
    public function __construct(
        private BriefingLimitEnforcer $limitEnforcer,
    ) {}

    /**
     * List available briefings for the workspace's plan.
     *
     * @param  Workspace  $workspace  The workspace
     */
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Briefing::class, $workspace]);

        // Get all active briefings
        $briefings = Briefing::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Filter to only those the workspace can access
        $accessibleBriefings = $briefings->filter(
            fn (Briefing $briefing): bool => $this->limitEnforcer->canGenerate($workspace, $briefing)['allowed']
        );

        return BriefingResource::collection($accessibleBriefings->values());
    }

    /**
     * Show a specific briefing.
     *
     * @param  Workspace  $workspace  The workspace
     * @param  string  $slug  The briefing slug
     */
    public function show(Workspace $workspace, string $slug): JsonResponse
    {
        $briefing = Briefing::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        Gate::authorize('view', [$briefing, $workspace]);

        // Check if workspace can generate this briefing
        $canGenerate = $this->limitEnforcer->canGenerate($workspace, $briefing);

        return response()->json([
            'data' => new BriefingResource($briefing),
            'can_generate' => $canGenerate['allowed'],
            'restriction_reason' => $canGenerate['reason'],
        ]);
    }
}
