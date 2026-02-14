<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Handlers;

use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Actions\Reviews\Loggers\ReviewRunActivityLogger;
use App\Actions\Reviews\Support\ReviewRunFinalizer;
use App\Enums\Reviews\SkipReason;
use App\Exceptions\NoProviderKeyException;
use App\Models\Run;
use App\Services\Reviews\ValueObjects\ReviewPolicy;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ReviewRunFailureHandler
{
    /**
     * Create a new failure handler instance.
     */
    public function __construct(
        private ReviewRunFinalizer $finalizer,
        private ReviewRunActivityLogger $activityLogger,
        private PostsSkipReasonComment $postSkipReasonComment,
    ) {}

    /**
     * Mark a run as skipped with a concrete skip reason.
     */
    public function markSkippedWithReason(Run $run, SkipReason $reason, string $message): Run
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
     * Mark a run as skipped when no provider keys are configured.
     */
    public function markNoProviderKeys(Run $run, ReviewPolicy $policy, NoProviderKeyException $exception): Run
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
     * Mark a run as failed and emit skip/failure comments and activity.
     */
    public function markFailed(Run $run, ReviewPolicy $policy, Throwable $exception): void
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
