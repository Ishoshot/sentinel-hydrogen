<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\Briefings\BriefingDownloadSource;
use App\Enums\Briefings\BriefingOutputFormat;
use App\Models\BriefingShare;
use App\Services\Briefings\ValueObjects\ViewSharedBriefingResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            Log::warning('Shared briefing access failed: invalid or expired token', [
                'token' => mb_substr($token, 0, 8).'...',
                'ip' => $request->ip(),
            ]);

            return ViewSharedBriefingResult::notFound();
        }

        if ($this->hasReachedMaxAccesses($share)) {
            Log::warning('Shared briefing access failed: max accesses reached', [
                'share_id' => $share->id,
                'access_count' => $share->access_count,
                'max_accesses' => $share->max_accesses,
                'ip' => $request->ip(),
            ]);

            return ViewSharedBriefingResult::maxAccessesReached();
        }

        if ($share->isPasswordProtected() && ! $this->verifyPassword($share, $password)) {
            Log::warning('Shared briefing access failed: incorrect password', [
                'share_id' => $share->id,
                'ip' => $request->ip(),
            ]);

            return ViewSharedBriefingResult::passwordRequired();
        }

        $share->loadMissing('generation.briefing', 'generation.generatedBy');
        $generation = $share->generation;

        if ($generation === null) {
            Log::warning('Shared briefing access failed: generation no longer available', [
                'share_id' => $share->id,
                'ip' => $request->ip(),
            ]);

            return ViewSharedBriefingResult::notFound('The briefing is no longer available.');
        }

        if (! $this->trackAccess($share, $generation, $request)) {
            Log::warning('Shared briefing access failed: concurrent max accesses reached', [
                'share_id' => $share->id,
                'ip' => $request->ip(),
            ]);

            return ViewSharedBriefingResult::maxAccessesReached();
        }

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
    ): bool {
        return DB::transaction(function () use ($share, $generation, $request): bool {
            $lockedShare = BriefingShare::query()
                ->whereKey($share->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedShare instanceof BriefingShare || $this->hasReachedMaxAccesses($lockedShare)) {
                return false;
            }

            $this->trackDownload->handle(
                generation: $generation,
                format: BriefingOutputFormat::Html,
                source: BriefingDownloadSource::ShareLink,
                request: $request,
            );

            $lockedShare->increment('access_count');

            return true;
        });
    }
}
