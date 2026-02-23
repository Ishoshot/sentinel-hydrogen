<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Activities\LogActivity;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Enums\Workspace\ActivityType;
use App\Models\Repository;
use App\Models\Run;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Supersedes active (queued/in-progress) runs for a pull request
 * when a newer push arrives, preventing stale reviews from executing.
 */
final readonly class SupersedeActiveRuns
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private UpdateRunAcknowledgmentComment $updateRunAcknowledgmentComment,
        private LogActivity $logActivity,
    ) {}

    /**
     * Supersede all active runs for the given repository and pull request.
     *
     * @return Collection<int, Run> The superseded runs.
     */
    public function handle(Repository $repository, int $pullRequestNumber): Collection
    {
        $activeRuns = Run::query()
            ->where('repository_id', $repository->id)
            ->where('pr_number', $pullRequestNumber)
            ->whereIn('status', [RunStatus::Queued, RunStatus::InProgress])
            ->get();

        if ($activeRuns->isEmpty()) {
            return $activeRuns;
        }

        $message = 'A newer commit was pushed to this pull request.';

        foreach ($activeRuns as $run) {
            $this->supersedeRun($run, $message);
        }

        Log::info('Superseded active runs for pull request', [
            'repository_id' => $repository->id,
            'pr_number' => $pullRequestNumber,
            'superseded_count' => $activeRuns->count(),
            'superseded_run_ids' => $activeRuns->pluck('id')->all(),
        ]);

        return $activeRuns;
    }

    /**
     * Transition a single run to the superseded state.
     */
    private function supersedeRun(Run $run, string $message): void
    {
        $metadata = $run->metadata ?? [];
        $metadata['skip_reason'] = SkipReason::Superseded->value;
        $metadata['skip_message'] = $message;

        $run->forceFill([
            'status' => RunStatus::Skipped,
            'completed_at' => now(),
            'metadata' => $metadata,
        ])->save();

        $this->updateRunAcknowledgmentComment->markSuperseded($run);
        $this->logSuperseded($run);
    }

    /**
     * Record activity for a superseded run.
     */
    private function logSuperseded(Run $run): void
    {
        $run->loadMissing('workspace');
        $workspace = $run->workspace;

        if ($workspace === null) {
            return;
        }

        $metadata = $run->metadata ?? [];
        $pullRequestNumber = is_int($metadata['pull_request_number'] ?? null) ? $metadata['pull_request_number'] : $run->pr_number;
        $repositoryFullName = is_string($metadata['repository_full_name'] ?? null) ? $metadata['repository_full_name'] : 'unknown';

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::RunSuperseded,
            description: sprintf('Review superseded for PR #%d in %s', $pullRequestNumber, $repositoryFullName),
            subject: $run,
            metadata: ['pull_request_number' => $pullRequestNumber],
        );
    }
}
