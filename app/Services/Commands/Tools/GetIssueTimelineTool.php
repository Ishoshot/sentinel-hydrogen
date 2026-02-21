<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Contracts\CommandToolBuilder;
use App\Services\Commands\Resolvers\IssueSnapshotResolver;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

final readonly class GetIssueTimelineTool implements CommandToolBuilder
{
    /**
     * Create a new GetIssueTimelineTool instance.
     */
    public function __construct(
        private IssueSnapshotResolver $issueSnapshotResolver,
        private ToolResultFormatter $formatter,
    ) {}

    /**
     * Build the get_issue_timeline tool for issue event timeline exploration.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool
    {
        return Tool::as('get_issue_timeline')
            ->for('Fetch timeline events for the current issue, including cross-references and commit references.')
            ->withNumberParameter('limit', 'Maximum number of most recent timeline events to return (default: 20, max: 60)')
            ->using(function (?int $limit = null) use ($commandRun, $pathRules): string {
                $snapshot = $this->issueSnapshotResolver->resolve($commandRun, includeTimeline: true);
                if (! $snapshot instanceof \App\Services\Commands\ValueObjects\IssueSnapshot || isset($snapshot->issue['pull_request'])) {
                    return 'Issue context unavailable. This tool only applies to commands triggered from non-PR issues.';
                }

                $eventLimit = max(1, min($limit ?? 20, 60));
                $timeline = $snapshot->timeline;
                if ($timeline === []) {
                    return 'No issue timeline events found.';
                }

                $recent = array_slice($timeline, -$eventLimit);
                $formatted = array_map(function (array $event, int $index) use ($pathRules): string {
                    $eventType = is_string($event['event'] ?? null) ? $event['event'] : 'unknown_event';
                    $actor = is_array($event['actor'] ?? null) && is_string($event['actor']['login'] ?? null)
                        ? $event['actor']['login']
                        : 'unknown';
                    $createdAt = is_string($event['created_at'] ?? null) ? $event['created_at'] : 'unknown';
                    $body = is_string($event['body'] ?? null) ? $event['body'] : '';
                    $body = $body !== '' ? "\n".$this->formatter->truncate($pathRules->redact($body), 220) : '';

                    $commitId = is_string($event['commit_id'] ?? null) ? $event['commit_id'] : null;
                    $commitPart = $commitId !== null ? sprintf(' | commit: %s', mb_substr($commitId, 0, 12)) : '';

                    return sprintf(
                        '[%d] %s by @%s (%s)%s%s',
                        $index + 1,
                        $eventType,
                        $actor,
                        $createdAt,
                        $commitPart,
                        $body
                    );
                }, $recent, array_keys($recent));

                return "Recent issue timeline events:\n\n".implode("\n\n---\n\n", $formatted);
            });
    }
}
