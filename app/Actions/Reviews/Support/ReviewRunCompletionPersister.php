<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

use App\Enums\Reviews\RunStatus;
use App\Models\Run;
use App\Services\Reviews\PersistReviewFindings;
use App\Services\Reviews\ValueObjects\PromptSnapshot;
use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewPolicy;
use App\Services\Reviews\ValueObjects\ReviewResult;
use Illuminate\Support\Facades\DB;

final readonly class ReviewRunCompletionPersister
{
    /**
     * Create a new completion persister instance.
     */
    public function __construct(private PersistReviewFindings $persistReviewFindings) {}

    /**
     * Persist completion state, metrics, and findings for a run.
     *
     * @param  array<int, ReviewFinding>  $filteredFindings
     */
    public function persist(
        Run $run,
        ReviewPolicy $policySnapshot,
        ReviewResult $reviewResult,
        array $filteredFindings,
        int $durationSeconds,
        int $durationMs,
    ): Run {
        $metrics = $reviewResult->metrics->toArray();
        $metrics['duration_ms'] = $durationMs;

        DB::transaction(function () use ($run, $policySnapshot, $reviewResult, $filteredFindings, $durationSeconds, $metrics): void {
            $metadata = $run->metadata ?? [];
            $metadata['review_summary'] = $reviewResult->summary->toArray();

            if ($reviewResult->promptSnapshot instanceof PromptSnapshot) {
                $metadata['prompt_snapshot'] = $reviewResult->promptSnapshot->toArray();
            }

            $run->forceFill([
                'status' => RunStatus::Completed,
                'completed_at' => now(),
                'duration_seconds' => $durationSeconds,
                'metrics' => $metrics,
                'policy_snapshot' => $policySnapshot->toArray(),
                'metadata' => $metadata,
            ])->save();

            if (! $run->findings()->exists()) {
                $this->persistReviewFindings->handle($run, $filteredFindings);
            }
        });

        $run->refresh();

        return $run;
    }
}
