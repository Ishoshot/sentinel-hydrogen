<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Normalizes GitHub pull request comments for context collection.
 */
final readonly class PullRequestCommentNormalizer
{
    /**
     * Maximum number of comments to include.
     */
    private const int MAX_COMMENTS = 20;

    /**
     * Common automated author patterns to ignore.
     *
     * @var array<int, string>
     */
    private const array BOT_PATTERNS = [
        '/\[bot\]$/i',
        '/^dependabot/i',
        '/^renovate/i',
        '/^github-actions/i',
        '/^codecov/i',
        '/^sonarcloud/i',
    ];

    /**
     * Normalize GitHub comment data to context format.
     *
     * @param  array<int, array<string, mixed>>  $rawComments
     * @return array<int, array{author: string, body: string, created_at: string}>
     */
    public function normalize(array $rawComments): array
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
     * Determine if the comment appears to come from a bot.
     */
    private function isBotComment(string $author, string $body): bool
    {
        foreach (self::BOT_PATTERNS as $pattern) {
            if (preg_match($pattern, $author) === 1) {
                return true;
            }
        }

        return str_contains($body, '<!-- sentinel-review -->')
            || str_contains($body, '<!-- sentinel-greeting -->');
    }
}
