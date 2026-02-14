<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Exceptions\NoProviderKeyException;
use App\Models\Run;
use App\Services\Reviews\ValueObjects\ReviewPolicy;
use Throwable;

final class ReviewRunFinalizer
{
    /**
     * Mark a run as skipped with mapped reason metadata.
     */
    public function markSkippedWithReason(Run $run, SkipReason $reason, string $message): Run
    {
        $metadata = $run->metadata ?? [];
        $metadata['skip_reason'] = $reason->value;
        $metadata['skip_message'] = $message;

        $run->forceFill([
            'status' => RunStatus::Skipped,
            'completed_at' => now(),
            'metadata' => $metadata,
        ])->save();

        return $run;
    }

    /**
     * Mark a run as skipped for missing provider keys.
     */
    public function markSkippedNoProviderKeys(Run $run, ReviewPolicy $policy, NoProviderKeyException $exception): Run
    {
        $metadata = $run->metadata ?? [];
        $metadata['skip_reason'] = 'no_provider_keys';
        $metadata['skip_message'] = $exception->getMessage();

        $this->finalizeRun($run, RunStatus::Skipped, $policy, $metadata);

        return $run;
    }

    /**
     * Mark a run as failed.
     */
    public function markFailed(Run $run, ReviewPolicy $policy, Throwable $exception): void
    {
        $metadata = $run->metadata ?? [];
        $metadata['review_failure'] = [
            'message' => $exception->getMessage(),
            'type' => $exception::class,
        ];

        $this->finalizeRun($run, RunStatus::Failed, $policy, $metadata);
    }

    /**
     * Convert exception class names into user-facing labels.
     */
    public function simpleErrorType(Throwable $exception): string
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
}
