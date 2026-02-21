<?php

declare(strict_types=1);

namespace App\Services\Commands\Formatters;

use App\Services\Context\SensitiveDataRedactor;

final readonly class IssueContextFormatter
{
    private const int MAX_ISSUE_BODY_CHARS = 3000;

    private const int MAX_COMMENTS = 12;

    private const int MAX_COMMENT_CHARS = 300;

    /**
     * Create a new IssueContextFormatter instance.
     */
    public function __construct(private SensitiveDataRedactor $sensitiveDataRedactor) {}

    /**
     * @param  array<string, mixed>  $issue
     * @param  array<int, array<string, mixed>>  $comments
     */
    public function format(array $issue, array $comments): string
    {
        $title = $this->sensitiveDataRedactor->redact((string) ($issue['title'] ?? 'Untitled issue'));
        $body = $this->sensitiveDataRedactor->redact((string) ($issue['body'] ?? 'No description provided.'));
        $state = (string) ($issue['state'] ?? 'open');
        $labels = $this->extractLabels($issue);
        $assignees = $this->extractAssignees($issue);
        $milestone = $this->extractMilestone($issue);
        $formattedComments = $this->formatComments($comments);

        if (mb_strlen($body) > self::MAX_ISSUE_BODY_CHARS) {
            $body = mb_substr($body, 0, self::MAX_ISSUE_BODY_CHARS)."\n... (description truncated)";
        }

        $context = <<<CTX
## Issue Context

**Title**: {$title}

**State**: {$state}

**Labels**: {$labels}

**Assignees**: {$assignees}

**Milestone**: {$milestone}

**Description**:
{$body}

CTX;

        if ($formattedComments !== '') {
            $context .= <<<CTX

### Recent Comments

{$formattedComments}

CTX;
        }

        return $context."---\n";
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function extractLabels(array $issue): string
    {
        $rawLabels = is_array($issue['labels'] ?? null) ? $issue['labels'] : [];

        $labels = array_values(array_filter(array_map(
            static fn (mixed $label): ?string => is_array($label) && is_string($label['name'] ?? null)
                ? $label['name']
                : null,
            $rawLabels
        )));

        $labelText = $labels === [] ? 'none' : implode(', ', $labels);

        return $this->sensitiveDataRedactor->redact($labelText);
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function extractAssignees(array $issue): string
    {
        $rawAssignees = is_array($issue['assignees'] ?? null) ? $issue['assignees'] : [];

        $assignees = array_values(array_filter(array_map(
            static fn (mixed $assignee): ?string => is_array($assignee) && is_string($assignee['login'] ?? null)
                ? '@'.$assignee['login']
                : null,
            $rawAssignees
        )));

        $assigneeText = $assignees === [] ? 'none' : implode(', ', $assignees);

        return $this->sensitiveDataRedactor->redact($assigneeText);
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function extractMilestone(array $issue): string
    {
        $milestone = is_array($issue['milestone'] ?? null) ? $issue['milestone'] : null;

        if (! is_array($milestone)) {
            return 'none';
        }

        return is_string($milestone['title'] ?? null) && $milestone['title'] !== ''
            ? $this->sensitiveDataRedactor->redact($milestone['title'])
            : 'none';
    }

    /**
     * @param  array<int, array<string, mixed>>  $comments
     */
    private function formatComments(array $comments): string
    {
        if ($comments === []) {
            return '';
        }

        $recentComments = array_slice($comments, -self::MAX_COMMENTS);

        $formatted = array_map(function (array $comment): string {
            $author = is_array($comment['user'] ?? null)
                ? (string) ($comment['user']['login'] ?? 'unknown')
                : 'unknown';
            $body = (string) ($comment['body'] ?? '');

            if ($body === '') {
                return '';
            }

            if (mb_strlen($body) > self::MAX_COMMENT_CHARS) {
                $body = mb_substr($body, 0, self::MAX_COMMENT_CHARS).'...';
            }

            return sprintf('**@%s**: %s', $author, $this->sensitiveDataRedactor->redact($body));
        }, $recentComments);

        $formatted = array_values(array_filter($formatted, static fn (string $entry): bool => $entry !== ''));

        return implode("\n\n", $formatted);
    }
}
