<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Guards;

use App\Actions\Reviews\UpdateRunAcknowledgmentComment;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Models\Run;
use Illuminate\Support\Facades\Log;

/**
 * Checks whether a newer run exists for the same pull request,
 * and marks the current run as superseded if so. This guards against
 * the race where a job is dequeued before SupersedeActiveRuns
 * could mark it during webhook processing.
 */
final readonly class ReviewRunSupersededGuard
{
    /**
     * Create a new guard instance.
     */
    public function __construct(private UpdateRunAcknowledgmentComment $updateRunAcknowledgmentComment) {}

    /**
     * Determine if the run has been superseded by a newer run.
     *
     * If superseded, the run is marked as skipped in-place.
     */
    public function isSuperseded(Run $run): bool
    {
        if ($run->pr_number === null) {
            return false;
        }

        $newerRunExists = Run::query()
            ->where('repository_id', $run->repository_id)
            ->where('pr_number', $run->pr_number)
            ->where('id', '>', $run->id)
            ->exists();

        if (! $newerRunExists) {
            return false;
        }

        $message = 'A newer commit was pushed to this pull request.';

        $metadata = $run->metadata ?? [];
        $metadata['skip_reason'] = SkipReason::Superseded->value;
        $metadata['skip_message'] = $message;

        $run->forceFill([
            'status' => RunStatus::Skipped,
            'completed_at' => now(),
            'metadata' => $metadata,
        ])->save();

        $this->updateRunAcknowledgmentComment->markSuperseded($run);

        Log::info('Review run superseded at execution time', [
            'run_id' => $run->id,
            'repository_id' => $run->repository_id,
            'pr_number' => $run->pr_number,
        ]);

        return true;
    }
}
