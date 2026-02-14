<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Loggers;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Exceptions\NoProviderKeyException;
use App\Models\Run;
use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewResult;
use Throwable;

final readonly class ReviewRunActivityLogger
{
    /**
     * Create a new review run activity logger.
     */
    public function __construct(private LogActivity $logActivity) {}

    /**
     * @param  array<int, ReviewFinding>  $filteredFindings
     */
    public function logCompleted(Run $run, ReviewResult $result, array $filteredFindings): void
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
     * Record activity details for a failed review run.
     */
    public function logFailed(Run $run, Throwable $exception): void
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
     * Record activity details for a skipped no-provider-keys run.
     */
    public function logSkippedNoProviderKeys(Run $run, NoProviderKeyException $exception): void
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
