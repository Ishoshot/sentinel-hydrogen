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
use App\Models\Finding;
use App\Models\Run;
use App\Services\Context\Contracts\ContextEngineContract;
use App\Services\Plans\PlanLimitEnforcer;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\Contracts\ReviewPolicyResolverContract;
use App\Services\Reviews\ValueObjects\PromptSnapshot;
use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewPolicy;
use App\Services\Reviews\ValueObjects\ReviewResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ExecuteReviewRun
{
    private const float DEFAULT_CONFIDENCE_THRESHOLD = 0.7;

    /**
     * Create a new action instance.
     */
    public function __construct(
        private ReviewPolicyResolverContract $policyResolver,
        private ContextEngineContract $contextEngine,
        private ReviewEngine $reviewEngine,
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
            // Build rich context using the Context Engine
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

            if (
                $branchConfig !== null
                && is_string($configBranch)
                && in_array($configBranch, $allowedBranches, true)
            ) {
                $policySnapshot = $this->policyResolver->resolve(
                    $repository,
                    $branchConfig,
                    $configBranch
                );
            }

            $context = [
                'repository' => $repository,
                'policy_snapshot' => $policySnapshot->toArray(),
                'context_bag' => $contextBag,
            ];

            $reviewResult = $this->reviewEngine->review($context);
            $filteredFindings = $this->applyPolicyLimits($reviewResult->findings, $policySnapshot);
        } catch (NoProviderKeyException $exception) {
            // Skip review gracefully - no BYOK keys configured
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

            if ($run->findings()->exists()) {
                return;
            }

            foreach ($filteredFindings as $finding) {
                $this->createFinding($run, $finding);
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
     * Create a finding record from a ReviewFinding value object.
     */
    private function createFinding(Run $run, ReviewFinding $finding): void
    {
        $findingHash = $this->generateFindingHash($finding);
        $duplicateExists = Finding::query()
            ->where('run_id', $run->id)
            ->where('finding_hash', $findingHash)
            ->exists();

        if ($duplicateExists) {
            return;
        }

        $metadata = array_filter([
            'impact' => $finding->impact !== '' ? $finding->impact : null,
            'current_code' => $finding->currentCode,
            'replacement_code' => $finding->replacementCode,
            'explanation' => $finding->explanation,
            'references' => $finding->references !== [] ? $finding->references : null,
        ], fn (mixed $value): bool => $value !== null);

        Finding::query()->create([
            'run_id' => $run->id,
            'finding_hash' => $findingHash,
            'workspace_id' => $run->workspace_id,
            'severity' => $finding->severity->value,
            'category' => $finding->category->value,
            'title' => $finding->title,
            'description' => $finding->description,
            'file_path' => $finding->filePath,
            'line_start' => $finding->lineStart,
            'line_end' => $finding->lineEnd,
            'confidence' => $finding->confidence,
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Generate a stable hash for a finding within a run.
     */
    private function generateFindingHash(ReviewFinding $finding): string
    {
        $payload = [
            'severity' => $finding->severity->value,
            'category' => $finding->category->value,
            'title' => $finding->title,
            'description' => $finding->description,
            'file_path' => $finding->filePath ?? '',
            'line_start' => $finding->lineStart,
            'line_end' => $finding->lineEnd,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            $encoded = serialize($payload);
        }

        return hash('sha256', $encoded);
    }

    /**
     * Apply policy limits to the findings list.
     *
     * @param  array<int, ReviewFinding>  $findings
     * @return array<int, ReviewFinding>
     */
    private function applyPolicyLimits(array $findings, ReviewPolicy $policy): array
    {
        if ($findings === []) {
            return [];
        }

        $minSeverity = $policy->getCommentSeverityThreshold();
        $maxFindings = $policy->getMaxInlineComments();
        $confidenceThreshold = $this->resolveConfidenceThreshold();
        $enabledRules = $this->resolveEnabledRules($policy);
        $ignoredPaths = $policy->ignoredPaths;

        $filtered = array_filter($findings, function (ReviewFinding $finding) use ($minSeverity, $confidenceThreshold, $enabledRules, $ignoredPaths): bool {
            if ($enabledRules !== null && ! in_array($finding->category->value, $enabledRules, true)) {
                return false;
            }

            if ($finding->filePath !== null && $this->matchesAnyPattern($finding->filePath, $ignoredPaths)) {
                return false;
            }

            if ($finding->severity->priority() < $minSeverity->priority()) {
                return false;
            }

            return $finding->confidence >= $confidenceThreshold;
        });

        $filtered = array_values($filtered);

        usort($filtered, fn (ReviewFinding $a, ReviewFinding $b): int => ($b->severity->priority() <=> $a->severity->priority())
            ?: ($b->confidence <=> $a->confidence)
            ?: $this->compareNullableStrings($a->filePath, $b->filePath)
            ?: $this->compareNullableInts($a->lineStart, $b->lineStart)
            ?: strcmp($a->title, $b->title)
        );

        if ($maxFindings < 1) {
            return [];
        }

        return array_slice($filtered, 0, $maxFindings);
    }

    /**
     * Resolve the list of enabled category rules from the policy.
     *
     * @return array<int, string>|null
     */
    private function resolveEnabledRules(ReviewPolicy $policy): ?array
    {
        return $policy->enabledRules === [] ? null : array_values($policy->enabledRules);
    }

    /**
     * Resolve the confidence threshold from the policy.
     */
    private function resolveConfidenceThreshold(): float
    {
        $defaultValue = config('reviews.default_policy.confidence_thresholds.finding', self::DEFAULT_CONFIDENCE_THRESHOLD);

        return is_numeric($defaultValue) ? (float) $defaultValue : self::DEFAULT_CONFIDENCE_THRESHOLD;
    }

    /**
     * Compare nullable strings, sorting nulls last.
     */
    private function compareNullableStrings(?string $left, ?string $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return strcmp($left, $right);
    }

    /**
     * Compare nullable integers, sorting nulls last.
     */
    private function compareNullableInts(?int $left, ?int $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return $left <=> $right;
    }

    /**
     * Check if a path matches any of the given glob patterns.
     *
     * @param  array<string>  $patterns
     */
    private function matchesAnyPattern(string $path, array $patterns): bool
    {
        if ($patterns === []) {
            return false;
        }

        return array_any($patterns, fn (string $pattern): bool => $this->matchesGlob($path, $pattern));
    }

    /**
     * Check if a path matches a glob pattern.
     *
     * Supports:
     * - * matches any sequence of characters except /
     * - ** matches any sequence including /
     * - ? matches any single character except /
     */
    private function matchesGlob(string $path, string $pattern): bool
    {
        if ($path === $pattern) {
            return true;
        }

        $regex = $this->globToRegex($pattern);

        return preg_match($regex, $path) === 1;
    }

    /**
     * Convert a glob pattern to a regex pattern.
     */
    private function globToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        $escaped = str_replace('\*\*', '.*', $escaped);
        $escaped = str_replace('\*', '[^\/]*', $escaped);
        $escaped = str_replace('\?', '[^\/]', $escaped);

        return '/^'.$escaped.'$/';
    }

    /**
     * Mark the run as skipped with a specific reason (before review execution).
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
     * Mark the run as skipped (e.g., no BYOK keys configured).
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
     * Mark the run as failed and log the error.
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
     * Finalize a run with the given status and metadata.
     *
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
     * Get a simple, user-friendly error type from an exception.
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
     * Log activity for a completed run.
     *
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
