<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\RecordBriefingFeedback;
use App\Http\Requests\Briefings\StoreBriefingFeedbackRequest;
use App\Models\BriefingGeneration;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingFeedbackTags;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final readonly class BriefingFeedbackController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private RecordBriefingFeedback $recordFeedback,
    ) {}

    /**
     * Store feedback for a briefing generation.
     */
    public function __invoke(
        StoreBriefingFeedbackRequest $request,
        Workspace $workspace,
        BriefingGeneration $generation,
    ): JsonResponse {
        if ($generation->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('view', $generation);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->recordFeedback->handle(
            generation: $generation,
            user: $user,
            rating: (int) $request->validated('rating'),
            comment: $request->validated('comment'),
            tags: BriefingFeedbackTags::fromArray($request->validated('tags') ?? []),
        );

        return response()->json([
            'message' => 'Feedback recorded.',
        ]);
    }
}
