<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Reviews\Guards\ReviewRunPreflightGuard;
use App\Actions\Reviews\Handlers\ReviewRunAnnotationHandler;
use App\Actions\Reviews\Handlers\ReviewRunCompletionHandler;
use App\Actions\Reviews\Handlers\ReviewRunFailureHandler;
use App\Actions\Reviews\Loggers\ReviewRunActivityLogger;
use App\Actions\Reviews\Resolvers\ReviewRunContextPolicyResolver;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Exceptions\NoProviderKeyException;
use App\Models\Run;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\FilterReviewFindings;
use Throwable;

final readonly class ExecuteReviewRun
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private ReviewRunPreflightGuard $preflightGuard,
        private ReviewRunContextPolicyResolver $contextPolicyResolver,
        private ReviewEngine $reviewEngine,
        private FilterReviewFindings $filterReviewFindings,
        private ReviewRunCompletionHandler $completionPersister,
        private ReviewRunActivityLogger $activityLogger,
        private ReviewRunFailureHandler $failureHandler,
        private ReviewRunAnnotationHandler $annotationHandler,
    ) {}

    /**
     * Execute the review run and store findings.
     */
    public function handle(Run $run): Run
    {
        $preflightResult = $this->preflightGuard->check($run);
        if (! $preflightResult->shouldProceed()) {
            if (
                $preflightResult->shouldSkip()
                && $preflightResult->skipReason instanceof SkipReason
                && is_string($preflightResult->skipMessage)
            ) {
                return $this->failureHandler->markSkippedWithReason($run, $preflightResult->skipReason, $preflightResult->skipMessage);
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
            return $this->failureHandler->markNoProviderKeys($run, $policySnapshot, $exception);
        } catch (Throwable $throwable) {
            $this->failureHandler->markFailed($run, $policySnapshot, $throwable);

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
        $this->annotationHandler->dispatchIfNeeded($run);

        return $run;
    }
}
