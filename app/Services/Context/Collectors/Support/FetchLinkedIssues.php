<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates fetching multiple linked issues with error handling and limits.
 */
final readonly class FetchLinkedIssues
{
    /**
     * Maximum number of issues to fetch to prevent API abuse.
     */
    private const int MAX_ISSUES = 5;

    /**
     * Create a new orchestrator instance.
     */
    public function __construct(
        private FetchLinkedIssue $issueFetcher = new FetchLinkedIssue,
    ) {}

    /**
     * Fetch all linked issues, applying limits and handling individual failures.
     *
     * @param  array<int>  $issueNumbers
     * @param  string  $fullName  Repository full name for logging
     * @return array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>
     */
    public function fetchAll(
        GitHubApiServiceContract $gitHubApiService,
        int $installationId,
        string $owner,
        string $repo,
        array $issueNumbers,
        string $fullName,
    ): array {
        $issueNumbers = array_slice($issueNumbers, 0, self::MAX_ISSUES);

        $linkedIssues = [];

        foreach ($issueNumbers as $issueNumber) {
            try {
                $issue = $this->issueFetcher->fetchIssueWithComments(
                    $gitHubApiService,
                    $installationId,
                    $owner,
                    $repo,
                    $issueNumber
                );

                if ($issue !== null) {
                    $linkedIssues[] = $issue;
                }
            } catch (Throwable $e) {
                Log::warning('LinkedIssueCollector: Failed to fetch issue', [
                    'issue_number' => $issueNumber,
                    'repository' => $fullName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $linkedIssues;
    }
}
