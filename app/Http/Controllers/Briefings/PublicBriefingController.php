<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\TrackBriefingDownload;
use App\Enums\BriefingDownloadSource;
use App\Enums\BriefingOutputFormat;
use App\Http\Resources\Briefings\BriefingGenerationResource;
use App\Models\BriefingShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class PublicBriefingController
{
    /**
     * Create a new controller instance.
     *
     * @param  TrackBriefingDownload  $trackDownload  Action to track downloads
     */
    public function __construct(
        private TrackBriefingDownload $trackDownload,
    ) {}

    /**
     * View a shared briefing.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $token  The share token
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $share = BriefingShare::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if ($share === null) {
            return response()->json([
                'message' => 'This share link is invalid or has expired.',
            ], 404);
        }

        // Check max accesses
        if ($share->max_accesses !== null && $share->access_count >= $share->max_accesses) {
            return response()->json([
                'message' => 'This share link has reached its maximum access limit.',
            ], 403);
        }

        // Check password if required
        if ($share->isPasswordProtected()) {
            $password = $request->input('password');
            if ($password === null || ! $share->verifyPassword($password)) {
                return response()->json([
                    'message' => 'This briefing is password protected.',
                    'requires_password' => true,
                ], 401);
            }
        }

        // Load the generation
        $share->loadMissing('generation.briefing');
        $generation = $share->generation;

        if ($generation === null) {
            return response()->json([
                'message' => 'The briefing is no longer available.',
            ], 404);
        }

        // Track the access
        $this->trackDownload->handle(
            generation: $generation,
            format: BriefingOutputFormat::Html,
            source: BriefingDownloadSource::ShareLink,
            request: $request,
        );

        // Increment access count
        $share->increment('access_count');

        return response()->json([
            'data' => new BriefingGenerationResource($generation),
        ]);
    }
}
