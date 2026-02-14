<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches and normalizes linked issue data from GitHub.
 */
final readonly class LinkedIssueFetcher
{
    /**
     * Maximum comments per issue to include.
     */
    private const int MAX_COMMENTS_PER_ISSUE = 10;

    /**
     * Fetch issue details with comments.
     *
     * @return array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}|null
     */
    public function fetchIssueWithComments(
        GitHubApiServiceContract $gitHubApiService,
        int $installationId,
        string $owner,
        string $repo,
        int $issueNumber
    ): ?array {
        $issue = $gitHubApiService->getIssue($installationId, $owner, $repo, $issueNumber);

        // @phpstan-ignore function.alreadyNarrowedType (defensive check against GitHub API changes)
        if (! is_array($issue)) {
            Log::debug('LinkedIssueCollector: Unexpected issue response format', [
                'issue_number' => $issueNumber,
            ]);

            return null;
        }

        // Skip if this is actually a PR (PRs are issues in GitHub API)
        if (isset($issue['pull_request'])) {
            return null;
        }

        $comments = $this->fetchIssueComments($gitHubApiService, $installationId, $owner, $repo, $issueNumber);

        return [
            'number' => $issueNumber,
            'title' => is_string($issue['title'] ?? null) ? $issue['title'] : '',
            'body' => is_string($issue['body'] ?? null) ? $issue['body'] : null,
            'state' => is_string($issue['state'] ?? null) ? $issue['state'] : 'open',
            'labels' => $this->extractLabels($issue),
            'comments' => $comments,
        ];
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<int, string>
     */
    private function extractLabels(array $issue): array
    {
        $labels = [];

        if (! isset($issue['labels']) || ! is_array($issue['labels'])) {
            return $labels;
        }

        foreach ($issue['labels'] as $label) {
            if (is_array($label) && isset($label['name']) && is_string($label['name'])) {
                $labels[] = $label['name'];
            }
        }

        return $labels;
    }

    /**
     * Fetch comments for an issue.
     *
     * @return array<int, array{author: string, body: string}>
     */
    private function fetchIssueComments(
        GitHubApiServiceContract $gitHubApiService,
        int $installationId,
        string $owner,
        string $repo,
        int $issueNumber
    ): array {
        try {
            $rawComments = $gitHubApiService->getIssueComments(
                $installationId,
                $owner,
                $repo,
                $issueNumber
            );

            // @phpstan-ignore function.alreadyNarrowedType (defensive check against GitHub API changes)
            if (! is_array($rawComments)) {
                Log::debug('LinkedIssueCollector: Unexpected comments response format', [
                    'issue_number' => $issueNumber,
                ]);

                return [];
            }

            $comments = [];
            $count = 0;

            foreach ($rawComments as $comment) {
                if ($count >= self::MAX_COMMENTS_PER_ISSUE) {
                    break;
                }

                $author = '';
                if (isset($comment['user']) && is_array($comment['user'])) {
                    $author = is_string($comment['user']['login'] ?? null) ? $comment['user']['login'] : '';
                }

                $body = is_string($comment['body'] ?? null) ? $comment['body'] : '';

                if ($body === '') {
                    continue;
                }

                $comments[] = [
                    'author' => $author,
                    'body' => $body,
                ];
                $count++;
            }

            return $comments;
        } catch (Throwable $throwable) {
            Log::debug('LinkedIssueCollector: Failed to fetch issue comments', [
                'issue_number' => $issueNumber,
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }
    }
}
