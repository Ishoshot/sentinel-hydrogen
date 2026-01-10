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
 * Collects PR discussion comments for review context.
 *
 * Fetches conversation/discussion comments on the pull request to provide
 * context about ongoing discussions and feedback.
 */
final readonly class PullRequestCommentCollector implements ContextCollector
{
    /**
     * Maximum number of comments to fetch.
     */
    private const int MAX_COMMENTS = 20;

    public function __construct(private GitHubApiService $gitHubApiService) {}

    public function name(): string
    {
        return 'pr_comments';
    }

    public function priority(): int
    {
        return 70; // Medium-high priority - discussion provides useful context
    }

    public function shouldCollect(array $params): bool
    {
        return isset($params['repository'], $params['run'])
            && $params['repository'] instanceof Repository
            && $params['run'] instanceof Run;
    }

    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        /** @var Run $run */
        $run = $params['run'];

        $metadata = $run->metadata ?? [];

        $repository->loadMissing('installation');
        $installation = $repository->installation;

        if ($installation === null) {
            return;
        }

        $fullName = $repository->full_name ?? '';
        if ($fullName === '' || ! str_contains($fullName, '/')) {
            return;
        }

        [$owner, $repo] = explode('/', $fullName, 2);
        $installationId = $installation->installation_id;
        $pullRequestNumber = is_int($metadata['pull_request_number'] ?? null)
            ? $metadata['pull_request_number']
            : 0;

        if ($pullRequestNumber <= 0) {
            return;
        }

        try {
            $rawComments = $this->gitHubApiService->getPullRequestComments(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber
            );

            // Validate response structure
            if (! is_array($rawComments)) {
                Log::warning('PullRequestCommentCollector: Unexpected response format from GitHub API', [
                    'pr_number' => $pullRequestNumber,
                ]);

                return;
            }

            $comments = $this->normalizeComments($rawComments);
            $bag->prComments = $comments;

            Log::info('PullRequestCommentCollector: Collected PR comments', [
                'repository' => $fullName,
                'pr_number' => $pullRequestNumber,
                'comments_count' => count($comments),
            ]);
        } catch (Throwable $e) {
            Log::warning('PullRequestCommentCollector: Failed to fetch PR comments', [
                'repository' => $fullName,
                'pr_number' => $pullRequestNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalize GitHub comment data to our format.
     *
     * @param  array<int, array<string, mixed>>  $rawComments
     * @return array<int, array{author: string, body: string, created_at: string}>
     */
    private function normalizeComments(array $rawComments): array
    {
        $comments = [];
        $count = 0;

        foreach ($rawComments as $comment) {
            if ($count >= self::MAX_COMMENTS) {
                break;
            }

            $author = '';
            if (isset($comment['user']) && is_array($comment['user'])) {
                $author = is_string($comment['user']['login'] ?? null) ? $comment['user']['login'] : '';
            }

            $body = is_string($comment['body'] ?? null) ? $comment['body'] : '';
            $createdAt = is_string($comment['created_at'] ?? null) ? $comment['created_at'] : '';

            // Skip empty comments or bot comments
            if ($body === '' || $this->isBotComment($author, $body)) {
                continue;
            }

            $comments[] = [
                'author' => $author,
                'body' => $body,
                'created_at' => $createdAt,
            ];
            $count++;
        }

        return $comments;
    }

    /**
     * Check if a comment appears to be from a bot.
     */
    private function isBotComment(string $author, string $body): bool
    {
        // Common bot usernames
        $botPatterns = [
            '/\[bot\]$/i',
            '/^dependabot/i',
            '/^renovate/i',
            '/^github-actions/i',
            '/^codecov/i',
            '/^sonarcloud/i',
        ];

        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $author) === 1) {
                return true;
            }
        }

        // Check for Sentinel's own comments to avoid circular context
        if (str_contains($body, '<!-- sentinel-review -->') || str_contains($body, '<!-- sentinel-greeting -->')) {
            return true;
        }

        return false;
    }
}
