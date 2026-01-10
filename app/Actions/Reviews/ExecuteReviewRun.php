<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\RunStatus;
use App\Models\Finding;
use App\Models\Run;
use App\Services\Reviews\Contracts\PullRequestDataResolver;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\ReviewPolicyResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ExecuteReviewRun
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private ReviewPolicyResolver $policyResolver,
        private PullRequestDataResolver $pullRequestDataResolver,
        private ReviewEngine $reviewEngine
    ) {}

    /**
     * Execute the review run and store findings.
     */
    public function handle(Run $run): Run
    {
        if (! in_array($run->status, [RunStatus::Queued, RunStatus::InProgress], true)) {
            return $run;
        }

        $run->loadMissing(['repository.settings', 'repository.installation']);

        $repository = $run->repository;

        if ($repository === null) {
            return $run;
        }

        $run->forceFill([
            'status' => RunStatus::InProgress,
            'started_at' => $run->started_at ?? now(),
        ])->save();

        $policySnapshot = $this->policyResolver->resolve($repository);

        $startTime = microtime(true);

        try {
            $pullRequestData = $this->pullRequestDataResolver->resolve($repository, $run);
            $context = [
                'run' => $run,
                'repository' => $repository,
                'policy_snapshot' => $policySnapshot,
                'pull_request' => $pullRequestData['pull_request'],
                'files' => $pullRequestData['files'],
                'metrics' => $pullRequestData['metrics'],
            ];

            $result = $this->reviewEngine->review($context);
        } catch (Throwable $throwable) {
            $this->markFailed($run, $policySnapshot, $throwable);

            throw $throwable;
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $metrics = $result['metrics'];
        $metrics['duration_ms'] = $durationMs;

        DB::transaction(function () use ($run, $policySnapshot, $result, $metrics): void {
            $metadata = $run->metadata ?? [];
            $metadata['review_summary'] = $result['summary'];

            $run->forceFill([
                'status' => RunStatus::Completed,
                'completed_at' => now(),
                'metrics' => $metrics,
                'policy_snapshot' => $policySnapshot,
                'metadata' => $metadata,
            ])->save();

            if ($run->findings()->exists()) {
                return;
            }

            foreach ($result['findings'] as $findingData) {
                $this->createFinding($run, $findingData);
            }
        });

        return $run->refresh();
    }

    /**
     * @param  array{severity: string, category: string, title: string, description: string, rationale: string, confidence: float, file_path?: string, line_start?: int, line_end?: int, suggestion?: string, patch?: string, references?: array<int, string>, tags?: array<int, string>}  $findingData
     */
    private function createFinding(Run $run, array $findingData): void
    {
        $metadata = Arr::only($findingData, ['suggestion', 'patch', 'references', 'tags', 'rationale']);

        if ($metadata === []) {
            $metadata = null;
        }

        Finding::query()->create([
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'severity' => $findingData['severity'],
            'category' => $findingData['category'],
            'title' => $findingData['title'],
            'description' => $findingData['description'],
            'file_path' => $findingData['file_path'] ?? null,
            'line_start' => $findingData['line_start'] ?? null,
            'line_end' => $findingData['line_end'] ?? null,
            'confidence' => $findingData['confidence'],
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Mark the run as failed and log the error.
     *
     * @param  array<string, mixed>  $policySnapshot
     */
    private function markFailed(Run $run, array $policySnapshot, Throwable $exception): void
    {
        $metadata = $run->metadata ?? [];
        $metadata['review_failure'] = [
            'message' => $exception->getMessage(),
            'type' => $exception::class,
        ];

        $run->forceFill([
            'status' => RunStatus::Failed,
            'completed_at' => now(),
            'policy_snapshot' => $policySnapshot,
            'metadata' => $metadata,
        ])->save();

        Log::error('Review run failed', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'exception' => $exception->getMessage(),
        ]);

    }
}
