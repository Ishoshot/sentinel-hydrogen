<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Actions\Reviews\Support\ReviewRunActivityLogger;
use App\Actions\Reviews\Support\ReviewRunCompletionPersister;
use App\Actions\Reviews\Support\ReviewRunContextPolicyResolver;
use App\Actions\Reviews\Support\ReviewRunFinalizer;
use App\Actions\Reviews\Support\ReviewRunPreflightChecker;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Exceptions\NoProviderKeyException;
use App\Jobs\Reviews\PostRunAnnotations;
use App\Models\Run;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\FilterReviewFindings;
use App\Services\Reviews\ValueObjects\ReviewPolicy;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ExecuteReviewRun
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private ReviewRunPreflightChecker $preflightChecker,
        private ReviewRunContextPolicyResolver $contextPolicyResolver,
        private ReviewEngine $reviewEngine,
        private FilterReviewFindings $filterReviewFindings,
        private ReviewRunCompletionPersister $completionPersister,
        private ReviewRunFinalizer $finalizer,
        private ReviewRunActivityLogger $activityLogger,
        private PostsSkipReasonComment $postSkipReasonComment,
    ) {}

    /**
     * Execute the review run and store findings.
     */
    public function handle(Run $run): Run
    {
        $preflightResult = $this->preflightChecker->check($run);
        if (! $preflightResult->shouldProceed()) {
            if (
                $preflightResult->shouldSkip()
                && $preflightResult->skipReason instanceof SkipReason
                && is_string($preflightResult->skipMessage)
            ) {
                return $this->markSkippedWithReason($run, $preflightResult->skipReason, $preflightResult->skipMessage);
            }

            return $run;
        }

        $repository = $run->repository;
        if ($repository === null) {
            return $run;
        }

        $run->forceFill([
            'status' => RunStatus::InProgress,
            'started_at' => $run->started_at ?? now(),
        ])->save();

        $policySnapshot = $this->contextPolicyResolver->defaultPolicy($repository);
        $startTime = microtime(true);

        try {
            $resolution = $this->contextPolicyResolver->resolve($repository, $run, $policySnapshot);
            $policySnapshot = $resolution->policySnapshot;
            $contextBag = $resolution->contextBag;

            $reviewResult = $this->reviewEngine->review([
                'repository' => $repository,
                'policy_snapshot' => $policySnapshot->toArray(),
                'context_bag' => $contextBag,
            ]);

            $filteredFindings = $this->filterReviewFindings->handle($reviewResult->findings, $policySnapshot);
        } catch (NoProviderKeyException $exception) {
            return $this->markSkipped($run, $policySnapshot, $exception);
        } catch (Throwable $throwable) {
            $this->markFailed($run, $policySnapshot, $throwable);

            throw $throwable;
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $durationSeconds = (int) round($durationMs / 1000);
        $run = $this->completionPersister->persist(
            $run,
            $policySnapshot,
            $reviewResult,
            $filteredFindings,
            $durationSeconds,
            $durationMs,
        );

        $this->activityLogger->logCompleted($run, $reviewResult, $filteredFindings);

        if ($run->findings()->exists()) {
            PostRunAnnotations::dispatch($run->id)->delay(now()->addSeconds(5));
        }

        return $run;
    }

    /**
     * Mark a run as skipped and post the mapped skip reason comment.
     */
    private function markSkippedWithReason(Run $run, SkipReason $reason, string $message): Run
    {
        $run = $this->finalizer->markSkippedWithReason($run, $reason, $message);

        Log::info('Review run skipped', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'reason' => $reason->value,
        ]);

        $this->postSkipReasonComment->handle($run, $reason, $message);

        return $run;
    }

    /**
     * Mark a run as skipped when provider keys are unavailable.
     */
    private function markSkipped(Run $run, ReviewPolicy $policy, NoProviderKeyException $exception): Run
    {
        $run = $this->finalizer->markSkippedNoProviderKeys($run, $policy, $exception);

        Log::info('Review run skipped - no BYOK provider keys configured', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'repository_id' => $run->repository_id,
        ]);

        $this->activityLogger->logSkippedNoProviderKeys($run, $exception);
        $this->postSkipReasonComment->handle($run, SkipReason::NoProviderKeys);

        return $run;
    }

    /**
     * Mark a run as failed and post a failure summary.
     */
    private function markFailed(Run $run, ReviewPolicy $policy, Throwable $exception): void
    {
        $this->finalizer->markFailed($run, $policy, $exception);

        Log::error('Review run failed', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'exception' => $exception->getMessage(),
        ]);

        $this->activityLogger->logFailed($run, $exception);
        $this->postSkipReasonComment->handle($run, SkipReason::RunFailed, $this->finalizer->simpleErrorType($exception));
    }
}
