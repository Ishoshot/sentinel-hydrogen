<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\GitHubApiService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects linked issues from PR body references.
 *
 * Parses patterns like "Fixes #123", "Closes #456", "Resolves #789" from the PR body
 * and fetches the issue details including comments.
 */
final readonly class LinkedIssueCollector implements ContextCollector
{
    /**
     * Maximum number of issues to fetch to prevent API abuse.
     */
    private const int MAX_ISSUES = 5;

    /**
     * Maximum comments per issue to include.
     */
    private const int MAX_COMMENTS_PER_ISSUE = 10;

    /**
     * Regex patterns for detecting issue references.
     *
     * @var array<string>
     */
    private const array ISSUE_PATTERNS = [
        '/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s*#(\d+)/i',
        '/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s+(\d+)/i',
        '/#(\d+)/',
    ];

    /**
     * Create a new LinkedIssueCollector instance.
     */
    public function __construct(private GitHubApiService $gitHubApiService) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'linked_issues';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 80; // High priority - issue context is important
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(array $params): bool
    {
        if (! isset($params['repository'], $params['run'])) {
            return false;
        }

        if (! $params['repository'] instanceof Repository || ! $params['run'] instanceof Run) {
            return false;
        }

        // Only collect if PR has a body that might contain issue references
        $metadata = $params['run']->metadata ?? [];
        $body = $metadata['pull_request_body'] ?? '';

        return is_string($body) && $body !== '';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        /** @var Run $run */
        $run = $params['run'];

        $metadata = $run->metadata ?? [];
        $body = is_string($metadata['pull_request_body'] ?? null) ? $metadata['pull_request_body'] : '';

        $repository->loadMissing('installation');
        $installation = $repository->installation;

        if ($installation === null) {
            return;
        }

        $fullName = $repository->full_name ?? '';
        if ($fullName === '' || ! str_contains((string) $fullName, '/')) {
            return;
        }

        [$owner, $repo] = explode('/', (string) $fullName, 2);
        $installationId = $installation->installation_id;

        // Extract issue numbers from PR body
        $issueNumbers = $this->extractIssueNumbers($body);

        if ($issueNumbers === []) {
            Log::debug('LinkedIssueCollector: No linked issues found in PR body');

            return;
        }

        // Limit the number of issues to fetch
        $issueNumbers = array_slice($issueNumbers, 0, self::MAX_ISSUES);

        $linkedIssues = [];

        foreach ($issueNumbers as $issueNumber) {
            try {
                $issue = $this->fetchIssueWithComments(
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

        $bag->linkedIssues = $linkedIssues;

        Log::info('LinkedIssueCollector: Collected linked issues', [
            'repository' => $fullName,
            'issues_found' => count($issueNumbers),
            'issues_fetched' => count($linkedIssues),
        ]);
    }

    /**
     * Extract issue numbers from PR body text.
     *
     * @return array<int>
     */
    private function extractIssueNumbers(string $body): array
    {
        $numbers = [];

        foreach (self::ISSUE_PATTERNS as $pattern) {
            if (preg_match_all($pattern, $body, $matches)) {
                foreach ($matches[1] as $match) {
                    $number = (int) $match;
                    if ($number > 0 && ! in_array($number, $numbers, true)) {
                        $numbers[] = $number;
                    }
                }
            }
        }

        return $numbers;
    }

    /**
     * Fetch issue details with comments.
     *
     * @return array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}|null
     */
    private function fetchIssueWithComments(
        int $installationId,
        string $owner,
        string $repo,
        int $issueNumber
    ): ?array {
        $issue = $this->gitHubApiService->getIssue($installationId, $owner, $repo, $issueNumber);

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

        $labels = [];
        if (isset($issue['labels']) && is_array($issue['labels'])) {
            foreach ($issue['labels'] as $label) {
                if (is_array($label) && isset($label['name']) && is_string($label['name'])) {
                    $labels[] = $label['name'];
                }
            }
        }

        $comments = $this->fetchIssueComments($installationId, $owner, $repo, $issueNumber);

        return [
            'number' => $issueNumber,
            'title' => is_string($issue['title'] ?? null) ? $issue['title'] : '',
            'body' => is_string($issue['body'] ?? null) ? $issue['body'] : null,
            'state' => is_string($issue['state'] ?? null) ? $issue['state'] : 'open',
            'labels' => $labels,
            'comments' => $comments,
        ];
    }

    /**
     * Fetch comments for an issue.
     *
     * @return array<int, array{author: string, body: string}>
     */
    private function fetchIssueComments(
        int $installationId,
        string $owner,
        string $repo,
        int $issueNumber
    ): array {
        try {
            $rawComments = $this->gitHubApiService->getIssueComments(
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

                if ($body !== '') {
                    $comments[] = [
                        'author' => $author,
                        'body' => $body,
                    ];
                    $count++;
                }
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
