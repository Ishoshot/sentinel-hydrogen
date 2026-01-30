<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\GenerateBriefing;
use App\Actions\Briefings\ListBriefingGenerations;
use App\Http\Requests\Briefings\GenerateBriefingRequest;
use App\Http\Requests\Briefings\ListBriefingGenerationsRequest;
use App\Http\Resources\Briefings\BriefingGenerationResource;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Workspace;
use App\Services\Briefings\BriefingLimitEnforcer;
use App\Services\Briefings\BriefingParameterValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final readonly class BriefingGenerationController
{
    /**
     * @param  BriefingLimitEnforcer  $limitEnforcer  Service to check plan limits
     * @param  BriefingParameterValidator  $parameterValidator  Service to validate parameters
     * @param  GenerateBriefing  $generateBriefing  Action to generate briefings
     * @param  ListBriefingGenerations  $listBriefingGenerations  Action to list generations
     */
    public function __construct(
        private BriefingLimitEnforcer $limitEnforcer,
        private BriefingParameterValidator $parameterValidator,
        private GenerateBriefing $generateBriefing,
        private ListBriefingGenerations $listBriefingGenerations,
    ) {}

    /** List briefing generations for the workspace with filters, search, and sorting. */
    public function index(ListBriefingGenerationsRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [BriefingGeneration::class, $workspace]);

        $generations = $this->listBriefingGenerations->handle(
            workspace: $workspace,
            search: $request->getSearch(),
            statuses: $request->getStatuses(),
            briefingId: $request->getBriefingId(),
            dateFrom: $request->getDateFrom(),
            dateTo: $request->getDateTo(),
            sort: $request->getSort(),
            direction: $request->getDirection(),
            perPage: $request->getPerPage(),
        );

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

        $parameters = $this->parameterValidator->validate($briefing, $request->input('parameters', []));

        $canGenerate = $this->limitEnforcer->canGenerate($workspace, $briefing, $parameters);

        if ($canGenerate->isDenied()) {
            return response()->json([
                'message' => $canGenerate->reason,
            ], 403);
        }

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

        $generation->load('briefing', 'generatedBy');

        return response()->json([
            'data' => new BriefingGenerationResource($generation),
        ]);
    }
}
