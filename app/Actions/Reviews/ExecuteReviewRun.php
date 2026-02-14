<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Activities\LogActivity;
use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Enums\Workspace\ActivityType;
use App\Exceptions\NoProviderKeyException;
use App\Jobs\Reviews\PostRunAnnotations;
use App\Models\Run;
use App\Services\Context\Contracts\ContextEngineContract;
use App\Services\Plans\PlanLimitEnforcer;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\Contracts\ReviewPolicyResolverContract;
use App\Services\Reviews\FilterReviewFindings;
use App\Services\Reviews\PersistReviewFindings;
use App\Services\Reviews\ValueObjects\PromptSnapshot;
use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewPolicy;
use App\Services\Reviews\ValueObjects\ReviewResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ExecuteReviewRun
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private ReviewPolicyResolverContract $policyResolver,
        private ContextEngineContract $contextEngine,
        private ReviewEngine $reviewEngine,
        private FilterReviewFindings $filterReviewFindings,
        private PersistReviewFindings $persistReviewFindings,
        private LogActivity $logActivity,
        private PostsSkipReasonComment $postSkipReasonComment,
        private PlanLimitEnforcer $planLimitEnforcer,
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

        $installation = $repository->installation;
        if ($installation === null || ! $installation->isActive()) {
            return $this->markSkippedWithReason($run, SkipReason::InstallationInactive, 'Installation is inactive or missing.');
        }

        $workspace = $run->workspace ?? $repository->workspace;
        if ($workspace !== null) {
            $subscriptionCheck = $this->planLimitEnforcer->ensureActiveSubscription($workspace);

            if (! $subscriptionCheck->allowed) {
                return $this->markSkippedWithReason(
                    $run,
                    SkipReason::PlanLimitReached,
                    $subscriptionCheck->message ?? 'Subscription is not active.'
                );
            }
        }

        $run->forceFill([
            'status' => RunStatus::InProgress,
            'started_at' => $run->started_at ?? now(),
        ])->save();

        $policySnapshot = $this->policyResolver->resolve($repository);
        $startTime = microtime(true);

        try {
            $contextBag = $this->contextEngine->build([
                'repository' => $repository,
                'run' => $run,
            ]);

            $sentinelConfigData = $contextBag->metadata['sentinel_config'] ?? null;
            $configBranch = $contextBag->metadata['config_from_branch'] ?? null;

            /** @var array<string, mixed>|null $branchConfig */
            $branchConfig = is_array($sentinelConfigData) ? $sentinelConfigData : null;

            $allowedBranches = array_values(array_unique(array_filter([
                $contextBag->pullRequest['base_branch'] ?? null,
                $repository->default_branch,
            ])));

            if ($branchConfig !== null && is_string($configBranch) && in_array($configBranch, $allowedBranches, true)) {
                $policySnapshot = $this->policyResolver->resolve($repository, $branchConfig, $configBranch);
            }

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
        $metrics = $reviewResult->metrics->toArray();
        $metrics['duration_ms'] = $durationMs;

        DB::transaction(function () use ($run, $policySnapshot, $reviewResult, $filteredFindings, $metrics, $durationSeconds): void {
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

        $this->logRunCompleted($run, $reviewResult, $filteredFindings);

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
        $metadata = $run->metadata ?? [];
        $metadata['skip_reason'] = $reason->value;
        $metadata['skip_message'] = $message;

        $run->forceFill([
            'status' => RunStatus::Skipped,
            'completed_at' => now(),
            'metadata' => $metadata,
        ])->save();

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
        $metadata = $run->metadata ?? [];
        $metadata['skip_reason'] = 'no_provider_keys';
        $metadata['skip_message'] = $exception->getMessage();

        $this->finalizeRun($run, RunStatus::Skipped, $policy, $metadata);

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
     * Mark a run as failed and post a failure summary.
     */
    private function markFailed(Run $run, ReviewPolicy $policy, Throwable $exception): void
    {
        $metadata = $run->metadata ?? [];
        $metadata['review_failure'] = [
            'message' => $exception->getMessage(),
            'type' => $exception::class,
        ];

        $this->finalizeRun($run, RunStatus::Failed, $policy, $metadata);

        Log::error('Review run failed', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'exception' => $exception->getMessage(),
        ]);

        $this->logRunFailed($run, $exception);
        $this->postSkipReasonComment->handle($run, SkipReason::RunFailed, $this->getSimpleErrorType($exception));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function finalizeRun(Run $run, RunStatus $status, ReviewPolicy $policy, array $metadata): void
    {
        $durationSeconds = $run->started_at !== null
            ? (int) now()->diffInSeconds($run->started_at, absolute: true)
            : null;

        $run->forceFill([
            'status' => $status,
            'completed_at' => now(),
            'duration_seconds' => $durationSeconds,
            'policy_snapshot' => $policy->toArray(),
            'metadata' => $metadata,
        ])->save();
    }

    /**
     * Convert exception classes into user-friendly error labels.
     */
    private function getSimpleErrorType(Throwable $exception): string
    {
        $shortName = class_basename($exception::class);

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
     * @param  array<int, ReviewFinding>  $filteredFindings
     */
    private function logRunCompleted(Run $run, ReviewResult $result, array $filteredFindings): void
    {
        $this->logRunActivity(
            $run,
            ActivityType::RunCompleted,
            'Review completed for PR #%d in %s',
            [
                'findings_count' => count($filteredFindings),
                'risk_level' => $result->summary->riskLevel->value,
            ]
        );
    }

    /**
     * Record activity details for failed review runs.
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
     * Record activity details for skipped review runs.
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
