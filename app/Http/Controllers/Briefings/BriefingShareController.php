<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\RevokeBriefingShare;
use App\Actions\Briefings\ShareBriefingGeneration;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Http\Requests\Briefings\CreateShareRequest;
use App\Http\Resources\Briefings\BriefingShareResource;
use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use App\Models\Workspace;
use App\Services\Briefings\BriefingLimitEnforcer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

final readonly class BriefingShareController
{
    /**
     * @param  BriefingLimitEnforcer  $limitEnforcer  Service to check plan limits
     * @param  ShareBriefingGeneration  $shareGeneration  Action to create shares
     * @param  RevokeBriefingShare  $revokeShare  Action to revoke shares
     */
    public function __construct(
        private BriefingLimitEnforcer $limitEnforcer,
        private ShareBriefingGeneration $shareGeneration,
        private RevokeBriefingShare $revokeShare,
    ) {}

    /** Create a share link for a generation. */
    public function store(
        CreateShareRequest $request,
        Workspace $workspace,
        BriefingGeneration $generation,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($generation->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('create', [BriefingShare::class, $workspace, $generation]);

        $canShare = $this->limitEnforcer->canShare();

        if ($canShare->isDenied()) {
            return response()->json([
                'message' => $canShare->reason,
            ], 403);
        }

        if ($generation->status !== BriefingGenerationStatus::Completed) {
            return response()->json([
                'message' => 'Cannot share a briefing that is not yet complete.',
            ], 400);
        }

        $expiresAt = null;

        if ($request->has('expires_at')) {
            $expiresAt = Carbon::parse($request->input('expires_at'));
        } elseif ($request->has('expires_in_days')) {
            $expiresAt = now()->addDays((int) $request->input('expires_in_days'));
        }

        $share = $this->shareGeneration->handle(
            generation: $generation,
            user: $user,
            expiresAt: $expiresAt,
            password: $request->input('password'),
            maxAccesses: $request->input('max_accesses'),
        );

        return response()->json([
            'data' => new BriefingShareResource($share),
            'message' => 'Share link created successfully.',
        ], 201);
    }

    /** Revoke a share link. */
    public function destroy(Workspace $workspace, BriefingShare $share): JsonResponse
    {
        if ($share->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('delete', $share);

        $this->revokeShare->handle($share);

        return response()->json([
            'message' => 'Share link revoked successfully.',
        ]);
    }
}
