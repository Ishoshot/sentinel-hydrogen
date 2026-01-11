<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\RunStatus;
use App\Exceptions\NoProviderKeyException;
use App\Jobs\Reviews\PostRunAnnotations;
use App\Models\Finding;
use App\Models\Run;
use App\Services\Context\Contracts\ContextEngineContract;
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
        private ContextEngineContract $contextEngine,
        private ReviewEngine $reviewEngine,
        private LogActivity $logActivity,
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
            // Build rich context using the Context Engine
            $contextBag = $this->contextEngine->build([
                'repository' => $repository,
                'run' => $run,
            ]);

            $context = [
                'repository' => $repository,
                'policy_snapshot' => $policySnapshot,
                'context_bag' => $contextBag,
            ];

            $result = $this->reviewEngine->review($context);
        } catch (NoProviderKeyException $exception) {
            // Skip review gracefully - no BYOK keys configured
            return $this->markSkipped($run, $policySnapshot, $exception);
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

        $run->refresh();

        $this->logRunCompleted($run, $result);

        if ($run->findings()->exists()) {
            PostRunAnnotations::dispatch($run->id)->delay(now()->addSeconds(5));
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $findingData
     */
    private function createFinding(Run $run, array $findingData): void
    {
        $metadata = Arr::only($findingData, [
            // Legacy fields
            'suggestion',
            'patch',
            'references',
            'tags',
            'rationale',
            // New enhanced fields
            'current_code',
            'replacement_code',
            'explanation',
        ]);

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
     * Mark the run as skipped (e.g., no BYOK keys configured).
     *
     * This is not an error condition - it simply means the repository
     * has not configured any AI provider keys for reviews.
     *
     * @param  array<string, mixed>  $policySnapshot
     */
    private function markSkipped(Run $run, array $policySnapshot, NoProviderKeyException $exception): Run
    {
        $metadata = $run->metadata ?? [];
        $metadata['skip_reason'] = 'no_provider_keys';
        $metadata['skip_message'] = $exception->getMessage();

        $run->forceFill([
            'status' => RunStatus::Skipped,
            'completed_at' => now(),
            'policy_snapshot' => $policySnapshot,
            'metadata' => $metadata,
        ])->save();

        Log::info('Review run skipped - no BYOK provider keys configured', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'repository_id' => $run->repository_id,
        ]);

        $this->logRunSkipped($run, $exception);

        return $run;
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

        $this->logRunFailed($run, $exception);
    }

    /**
     * Log activity for a completed run.
     *
     * @param  array{summary: array<string, mixed>, findings: array<int, array<string, mixed>>, metrics: array<string, mixed>}  $result
     */
    private function logRunCompleted(Run $run, array $result): void
    {
        $run->loadMissing('workspace');
        $workspace = $run->workspace;

        if ($workspace === null) {
            return;
        }

        $metadata = $run->metadata ?? [];
        $pullRequestNumber = is_int($metadata['pull_request_number'] ?? null) ? $metadata['pull_request_number'] : 0;
        $repositoryFullName = is_string($metadata['repository_full_name'] ?? null) ? $metadata['repository_full_name'] : 'unknown';

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::RunCompleted,
            description: sprintf(
                'Review completed for PR #%d in %s',
                $pullRequestNumber,
                $repositoryFullName
            ),
            subject: $run,
            metadata: [
                'findings_count' => count($result['findings']),
                'risk_level' => $result['summary']['risk_level'],
                'pull_request_number' => $pullRequestNumber,
            ],
        );
    }

    /**
     * Log activity for a failed run.
     */
    private function logRunFailed(Run $run, Throwable $exception): void
    {
        $run->loadMissing('workspace');
        $workspace = $run->workspace;

        if ($workspace === null) {
            return;
        }

        $metadata = $run->metadata ?? [];
        $pullRequestNumber = is_int($metadata['pull_request_number'] ?? null) ? $metadata['pull_request_number'] : 0;
        $repositoryFullName = is_string($metadata['repository_full_name'] ?? null) ? $metadata['repository_full_name'] : 'unknown';

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::RunFailed,
            description: sprintf(
                'Review failed for PR #%d in %s',
                $pullRequestNumber,
                $repositoryFullName
            ),
            subject: $run,
            metadata: [
                'error_type' => $exception::class,
                'error_message' => $exception->getMessage(),
                'pull_request_number' => $pullRequestNumber,
            ],
        );
    }

    /**
     * Log activity for a skipped run.
     */
    private function logRunSkipped(Run $run, NoProviderKeyException $exception): void
    {
        $run->loadMissing('workspace');
        $workspace = $run->workspace;

        if ($workspace === null) {
            return;
        }

        $metadata = $run->metadata ?? [];
        $pullRequestNumber = is_int($metadata['pull_request_number'] ?? null) ? $metadata['pull_request_number'] : 0;
        $repositoryFullName = is_string($metadata['repository_full_name'] ?? null) ? $metadata['repository_full_name'] : 'unknown';

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::RunSkipped,
            description: sprintf(
                'Review skipped for PR #%d in %s - no provider keys configured',
                $pullRequestNumber,
                $repositoryFullName
            ),
            subject: $run,
            metadata: [
                'skip_reason' => 'no_provider_keys',
                'skip_message' => $exception->getMessage(),
                'pull_request_number' => $pullRequestNumber,
            ],
        );
    }
}
