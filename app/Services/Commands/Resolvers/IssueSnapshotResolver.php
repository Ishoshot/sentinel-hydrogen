<?php

declare(strict_types=1);

namespace App\Services\Commands\Resolvers;

use App\Models\CommandRun;
use App\Services\Commands\ValueObjects\IssueSnapshot;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

final class IssueSnapshotResolver
{
    /**
     * @var array<string, IssueSnapshot>
     */
    private array $snapshotCache = [];

    /**
     * @var array<string, array{issue: int, comments: int, timeline: int}>
     */
    private array $apiCallCounters = [];

    /**
     * Create a new IssueSnapshotResolver instance.
     */
    public function __construct(
        private readonly GitHubApiServiceContract $gitHubApiService,
        private readonly IssueApiParameterResolver $issueApiParameterResolver,
    ) {}

    /**
     * Resolve issue data snapshot for a command run.
     */
    public function resolve(CommandRun $commandRun, bool $includeTimeline = false): ?IssueSnapshot
    {
        try {
            $issueParams = $this->issueApiParameterResolver->resolve($commandRun);
            if ($issueParams === null) {
                return null;
            }

            [$installationId, $owner, $repo, $issueNumber] = $issueParams;
            $cacheKey = $this->buildCacheKey($installationId, $owner, $repo, $issueNumber);
            $cacheHit = isset($this->snapshotCache[$cacheKey]);

            if (! $cacheHit) {
                $this->initializeCounters($cacheKey);

                $issue = $this->gitHubApiService->getIssue($installationId, $owner, $repo, $issueNumber);
                $this->incrementCounter($cacheKey, 'issue');

                $comments = $this->gitHubApiService->getIssueComments($installationId, $owner, $repo, $issueNumber);
                $this->incrementCounter($cacheKey, 'comments');

                $timeline = [];
                if ($includeTimeline) {
                    $timeline = $this->gitHubApiService->getIssueTimeline($installationId, $owner, $repo, $issueNumber);
                    $this->incrementCounter($cacheKey, 'timeline');
                }

                $this->snapshotCache[$cacheKey] = new IssueSnapshot(
                    issue: $issue,
                    comments: $comments,
                    timeline: $timeline,
                    hasTimeline: $includeTimeline,
                );
            } elseif ($includeTimeline && ! $this->snapshotCache[$cacheKey]->hasTimeline) {
                $snapshot = $this->snapshotCache[$cacheKey];
                $timeline = $this->gitHubApiService->getIssueTimeline($installationId, $owner, $repo, $issueNumber);
                $this->incrementCounter($cacheKey, 'timeline');

                $this->snapshotCache[$cacheKey] = new IssueSnapshot(
                    issue: $snapshot->issue,
                    comments: $snapshot->comments,
                    timeline: $timeline,
                    hasTimeline: true,
                );
            }

            $snapshot = $this->snapshotCache[$cacheKey];
            $calls = $this->apiCallCounters[$cacheKey] ?? ['issue' => 0, 'comments' => 0, 'timeline' => 0];

            Log::debug('Issue snapshot resolved', [
                'command_run_id' => $commandRun->id,
                'repository' => $commandRun->repository?->full_name,
                'issue_number' => $issueNumber,
                'include_timeline' => $includeTimeline,
                'cache_hit' => $cacheHit,
                'api_calls_issue' => $calls['issue'],
                'api_calls_comments' => $calls['comments'],
                'api_calls_timeline' => $calls['timeline'],
            ]);

            return $snapshot;
        } catch (Throwable $throwable) {
            Log::warning('Failed to resolve issue snapshot', [
                'command_run_id' => $commandRun->id,
                'repository' => $commandRun->repository?->full_name,
                'issue_number' => $commandRun->issue_number,
                'include_timeline' => $includeTimeline,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build a stable cache key for issue snapshot memoization.
     */
    private function buildCacheKey(int $installationId, string $owner, string $repo, int $issueNumber): string
    {
        return sprintf('%d:%s:%s:%d', $installationId, $owner, $repo, $issueNumber);
    }

    /**
     * Ensure API call counters are initialized for a cache key.
     */
    private function initializeCounters(string $cacheKey): void
    {
        if (! isset($this->apiCallCounters[$cacheKey])) {
            $this->apiCallCounters[$cacheKey] = [
                'issue' => 0,
                'comments' => 0,
                'timeline' => 0,
            ];
        }
    }

    /**
     * Increment a named API call counter for telemetry.
     *
     * @param  'issue'|'comments'|'timeline'  $counter
     */
    private function incrementCounter(string $cacheKey, string $counter): void
    {
        $this->initializeCounters($cacheKey);

        $counters = $this->apiCallCounters[$cacheKey];
        $counters[$counter] += 1;

        $this->apiCallCounters[$cacheKey] = [
            'issue' => $counters['issue'],
            'comments' => $counters['comments'],
            'timeline' => $counters['timeline'],
        ];
    }
}
