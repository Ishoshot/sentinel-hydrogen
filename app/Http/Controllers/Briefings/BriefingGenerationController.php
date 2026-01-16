<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\GenerateBriefing;
use App\Http\Requests\Briefings\GenerateBriefingRequest;
use App\Http\Resources\Briefings\BriefingGenerationResource;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Workspace;
use App\Services\Briefings\BriefingLimitEnforcer;
use App\Services\Briefings\BriefingParameterValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final readonly class BriefingGenerationController
{
    /**
     * @param  BriefingLimitEnforcer  $limitEnforcer  Service to check plan limits
     * @param  BriefingParameterValidator  $parameterValidator  Service to validate parameters
     * @param  GenerateBriefing  $generateBriefing  Action to generate briefings
     */
    public function __construct(
        private BriefingLimitEnforcer $limitEnforcer,
        private BriefingParameterValidator $parameterValidator,
        private GenerateBriefing $generateBriefing,
    ) {}

    /** List briefing generations for the workspace. */
    public function index(Request $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [BriefingGeneration::class, $workspace]);

        $perPage = min((int) $request->query('per_page', '20'), 100);

        $generations = BriefingGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->with('briefing')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return BriefingGenerationResource::collection($generations);
    }

    /** Generate a new briefing. */
    public function store(GenerateBriefingRequest $request, Workspace $workspace, string $slug): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $briefing = Briefing::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        Gate::authorize('create', [BriefingGeneration::class, $workspace, $briefing]);

        $canGenerate = $this->limitEnforcer->canGenerate($workspace, $briefing);

        if (! $canGenerate['allowed']) {
            return response()->json([
                'message' => $canGenerate['reason'],
            ], 403);
        }

        $parameters = $this->parameterValidator->validate($briefing, $request->input('parameters', []));

        $generation = $this->generateBriefing->handle(
            workspace: $workspace,
            briefing: $briefing,
            user: $user,
            parameters: $parameters,
        );

        return response()->json([
            'data' => new BriefingGenerationResource($generation),
            'message' => 'Briefing generation started.',
        ], 201);
    }

    /** Show a specific generation. */
    public function show(Workspace $workspace, BriefingGeneration $generation): JsonResponse
    {
        if ($generation->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('view', $generation);

        $generation->load('briefing');

        return response()->json([
            'data' => new BriefingGenerationResource($generation),
        ]);
    }
}
