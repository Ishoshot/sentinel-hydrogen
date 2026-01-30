<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\ListAccessibleBriefings;
use App\Http\Resources\Briefings\BriefingResource;
use App\Models\Briefing;
use App\Models\Workspace;
use App\Services\Briefings\BriefingLimitEnforcer;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final readonly class BriefingController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private BriefingLimitEnforcer $limitEnforcer,
        private ListAccessibleBriefings $listAccessibleBriefings,
    ) {}

    /**
     * List available briefings for the workspace's plan.
     */
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Briefing::class, $workspace]);

        $briefings = $this->listAccessibleBriefings->handle();

        return BriefingResource::collection($briefings);
    }

    /**
     * Show a specific briefing.
     */
    public function show(Workspace $workspace, string $slug): JsonResponse
    {
        $briefing = Briefing::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        Gate::authorize('view', [$briefing, $workspace]);

        $parameters = BriefingParameters::fromArray([]);
        $canGenerate = $this->limitEnforcer->canGenerate($workspace, $briefing, $parameters);

        return response()->json([
            'data' => new BriefingResource($briefing),
            'can_generate' => $canGenerate->allowed,
            'restriction_reason' => $canGenerate->reason,
        ]);
    }
}
