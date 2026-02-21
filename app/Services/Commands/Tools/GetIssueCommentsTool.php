<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Contracts\CommandToolBuilder;
use App\Services\Commands\Resolvers\IssueSnapshotResolver;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

final readonly class GetIssueCommentsTool implements CommandToolBuilder
{
    /**
     * Create a new GetIssueCommentsTool instance.
     */
    public function __construct(
        private IssueSnapshotResolver $issueSnapshotResolver,
        private ToolResultFormatter $formatter,
    ) {}

    /**
     * Build the get_issue_comments tool for issue command context exploration.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool
    {
        return Tool::as('get_issue_comments')
            ->for('Fetch recent comments from the current GitHub issue thread. Use this to understand discussion context and constraints.')
            ->withNumberParameter('limit', 'Maximum number of recent comments to return (default: 20, max: 50)')
            ->using(function (?int $limit = null) use ($commandRun, $pathRules): string {
                $snapshot = $this->issueSnapshotResolver->resolve($commandRun);
                if (! $snapshot instanceof \App\Services\Commands\ValueObjects\IssueSnapshot || isset($snapshot->issue['pull_request'])) {
                    return 'Issue context unavailable. This tool only applies to commands triggered from non-PR issues.';
                }

                $commentLimit = max(1, min($limit ?? 20, 50));
                $comments = $snapshot->comments;
                if ($comments === []) {
                    return 'No issue comments found.';
                }

                $recent = array_slice($comments, -$commentLimit);
                $formatted = array_map(function (array $comment, int $index) use ($pathRules): string {
                    $author = is_array($comment['user'] ?? null) && is_string($comment['user']['login'] ?? null)
                        ? $comment['user']['login']
                        : 'unknown';
                    $body = is_string($comment['body'] ?? null) ? $comment['body'] : '';
                    $body = $this->formatter->truncate($pathRules->redact($body), 280);

                    $createdAt = is_string($comment['created_at'] ?? null) ? $comment['created_at'] : 'unknown';

                    return sprintf("[%d] @%s (%s)\n%s", $index + 1, $author, $createdAt, $body);
                }, $recent, array_keys($recent));

                return "Recent issue comments:\n\n".implode("\n\n---\n\n", $formatted);
            });
    }
}
