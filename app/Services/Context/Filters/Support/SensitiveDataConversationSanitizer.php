<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\SensitiveDataRedactor;

final readonly class SensitiveDataConversationSanitizer
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private SensitiveDataRedactor $redactor,
    ) {}

    /**
     * @param  array<string, mixed>  $pullRequest
     * @return array<string, mixed>
     */
    public function sanitizePullRequest(array $pullRequest, int &$redactedCount): array
    {
        if (isset($pullRequest['body']) && is_string($pullRequest['body'])) {
            $original = $pullRequest['body'];
            $pullRequest['body'] = $this->redactor->redact($pullRequest['body']);

            if ($original !== $pullRequest['body']) {
                $redactedCount++;
            }
        }

        return $pullRequest;
    }

    /**
     * @param  array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>  $linkedIssues
     * @return array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>
     */
    public function sanitizeLinkedIssues(array $linkedIssues, int &$redactedCount): array
    {
        return array_map(function (array $issue) use (&$redactedCount): array {
            if ($issue['body'] !== null) {
                $original = $issue['body'];
                $issue['body'] = $this->redactor->redact($issue['body']);

                if ($original !== $issue['body']) {
                    $redactedCount++;
                }
            }

            $issue['comments'] = array_map(function (array $comment) use (&$redactedCount): array {
                $original = $comment['body'];
                $comment['body'] = $this->redactor->redact($comment['body']);

                if ($original !== $comment['body']) {
                    $redactedCount++;
                }

                return $comment;
            }, $issue['comments']);

            return $issue;
        }, $linkedIssues);
    }

    /**
     * @param  array<int, array{author: string, body: string, created_at: string}>  $prComments
     * @return array<int, array{author: string, body: string, created_at: string}>
     */
    public function sanitizePrComments(array $prComments, int &$redactedCount): array
    {
        return array_map(function (array $comment) use (&$redactedCount): array {
            $original = $comment['body'];
            $comment['body'] = $this->redactor->redact($comment['body']);

            if ($original !== $comment['body']) {
                $redactedCount++;
            }

            return $comment;
        }, $prComments);
    }
}
