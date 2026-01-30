<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\Briefings\BriefingDownloadSource;
use App\Enums\Briefings\BriefingOutputFormat;
use App\Models\BriefingShare;
use App\Services\Briefings\ValueObjects\ViewSharedBriefingResult;
use Illuminate\Http\Request;

/**
 * View a shared briefing, validating access and tracking the view.
 */
final readonly class ViewSharedBriefing
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private TrackBriefingDownload $trackDownload,
    ) {}

    /**
     * Attempt to view a shared briefing.
     */
    public function handle(string $token, ?string $password, Request $request): ViewSharedBriefingResult
    {
        $share = $this->findActiveShare($token);

        if (! $share instanceof BriefingShare) {
            return ViewSharedBriefingResult::notFound();
        }

        if ($this->hasReachedMaxAccesses($share)) {
            return ViewSharedBriefingResult::maxAccessesReached();
        }

        if ($share->isPasswordProtected() && ! $this->verifyPassword($share, $password)) {
            return ViewSharedBriefingResult::passwordRequired();
        }

        $share->loadMissing('generation.briefing', 'generation.generatedBy');
        $generation = $share->generation;

        if ($generation === null) {
            return ViewSharedBriefingResult::notFound('The briefing is no longer available.');
        }

        $this->trackAccess($share, $generation, $request);

        return ViewSharedBriefingResult::success($generation);
    }

    /**
     * Find an active, non-expired share.
     */
    private function findActiveShare(string $token): ?BriefingShare
    {
        return BriefingShare::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Check if the share has reached its maximum access limit.
     */
    private function hasReachedMaxAccesses(BriefingShare $share): bool
    {
        return $share->max_accesses !== null && $share->access_count >= $share->max_accesses;
    }

    /**
     * Verify the password for a password-protected share.
     */
    private function verifyPassword(BriefingShare $share, ?string $password): bool
    {
        return $password !== null && $share->verifyPassword($password);
    }

    /**
     * Track the access and increment the counter.
     */
    private function trackAccess(
        BriefingShare $share,
        \App\Models\BriefingGeneration $generation,
        Request $request,
    ): void {
        $this->trackDownload->handle(
            generation: $generation,
            format: BriefingOutputFormat::Html,
            source: BriefingDownloadSource::ShareLink,
            request: $request,
        );

        $share->increment('access_count');
    }
}
