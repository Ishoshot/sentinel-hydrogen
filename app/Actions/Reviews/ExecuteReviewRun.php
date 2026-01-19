<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Activities\LogActivity;
use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Enums\ActivityType;
use App\Enums\RunStatus;
use App\Enums\SkipReason;
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
        private PostsSkipReasonComment $postSkipReasonComment,
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
        $durationSeconds = (int) round($durationMs / 1000);
        $metrics = $result['metrics'];
        $metrics['duration_ms'] = $durationMs;

        DB::transaction(function () use ($run, $policySnapshot, $result, $metrics, $durationSeconds): void {
            $metadata = $run->metadata ?? [];
            $metadata['review_summary'] = $result['summary'];

            $run->forceFill([
                'status' => RunStatus::Completed,
                'completed_at' => now(),
                'duration_seconds' => $durationSeconds,
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
            'impact',
            'current_code',
            'replacement_code',
            'explanation',
            'references',
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
     * @param  array<string, mixed>  $policySnapshot
     */
    private function markSkipped(Run $run, array $policySnapshot, NoProviderKeyException $exception): Run
    {
        $metadata = $run->metadata ?? [];
        $metadata['skip_reason'] = 'no_provider_keys';
        $metadata['skip_message'] = $exception->getMessage();

        $this->finalizeRun($run, RunStatus::Skipped, $policySnapshot, $metadata);

        Log::info('Review run skipped - no BYOK provider keys configured', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'repository_id' => $run->repository_id,
        ]);

        $this->logRunSkipped($run, $exception);
        $this->postSkipReasonComment->handle($run, SkipReason::NoProviderKeys);

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

        $this->finalizeRun($run, RunStatus::Failed, $policySnapshot, $metadata);

        Log::error('Review run failed', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'exception' => $exception->getMessage(),
        ]);

        $this->logRunFailed($run, $exception);
        $this->postSkipReasonComment->handle($run, SkipReason::RunFailed, $this->getSimpleErrorType($exception));
    }

    /**
     * Finalize a run with the given status and metadata.
     *
     * @param  array<string, mixed>  $policySnapshot
     * @param  array<string, mixed>  $metadata
     */
    private function finalizeRun(Run $run, RunStatus $status, array $policySnapshot, array $metadata): void
    {
        $durationSeconds = $run->started_at !== null
            ? (int) now()->diffInSeconds($run->started_at, absolute: true)
            : null;

        $run->forceFill([
            'status' => $status,
            'completed_at' => now(),
            'duration_seconds' => $durationSeconds,
            'policy_snapshot' => $policySnapshot,
            'metadata' => $metadata,
        ])->save();
    }

    /**
     * Get a simple, user-friendly error type from an exception.
     */
    private function getSimpleErrorType(Throwable $exception): string
    {
        $className = $exception::class;
        $shortName = class_basename($className);

        return match (true) {
            str_contains($shortName, 'Timeout') => 'Request Timeout',
            str_contains($shortName, 'Connection') => 'Connection Error',
            str_contains($shortName, 'RateLimit') => 'Rate Limit Exceeded',
            str_contains($shortName, 'Authentication') => 'Authentication Error',
            str_contains($shortName, 'Authorization') => 'Authorization Error',
            str_contains($shortName, 'Validation') => 'Validation Error',
            default => 'Internal Error',
        };
    }

    /**
     * Log activity for a completed run.
     *
     * @param  array{summary: array<string, mixed>, findings: array<int, array<string, mixed>>, metrics: array<string, mixed>}  $result
     */
    private function logRunCompleted(Run $run, array $result): void
    {
        $this->logRunActivity(
            $run,
            ActivityType::RunCompleted,
            'Review completed for PR #%d in %s',
            [
                'findings_count' => count($result['findings']),
                'risk_level' => $result['summary']['risk_level'],
            ]
        );
    }

    /**
     * Log activity for a failed run.
     */
    private function logRunFailed(Run $run, Throwable $exception): void
    {
        $this->logRunActivity(
            $run,
            ActivityType::RunFailed,
            'Review failed for PR #%d in %s',
            [
                'error_type' => $exception::class,
                'error_message' => $exception->getMessage(),
            ]
        );
    }

    /**
     * Log activity for a skipped run.
     */
    private function logRunSkipped(Run $run, NoProviderKeyException $exception): void
    {
        $this->logRunActivity(
            $run,
            ActivityType::RunSkipped,
            'Review skipped for PR #%d in %s - no provider keys configured',
            [
                'skip_reason' => 'no_provider_keys',
                'skip_message' => $exception->getMessage(),
            ]
        );
    }

    /**
     * Log activity for a run with common metadata extraction.
     *
     * @param  array<string, mixed>  $additionalMetadata
     */
    private function logRunActivity(Run $run, ActivityType $type, string $descriptionFormat, array $additionalMetadata = []): void
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
            type: $type,
            description: sprintf($descriptionFormat, $pullRequestNumber, $repositoryFullName),
            subject: $run,
            metadata: array_merge(['pull_request_number' => $pullRequestNumber], $additionalMetadata),
        );
    }
}
