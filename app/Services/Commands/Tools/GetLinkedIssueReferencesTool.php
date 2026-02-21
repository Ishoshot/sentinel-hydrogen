<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Contracts\CommandToolBuilder;
use App\Services\Commands\Resolvers\IssueLinkedReferencesResolver;
use App\Services\Commands\Resolvers\IssueSnapshotResolver;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

final readonly class GetLinkedIssueReferencesTool implements CommandToolBuilder
{
    /**
     * Create a new GetLinkedIssueReferencesTool instance.
     */
    public function __construct(
        private IssueSnapshotResolver $issueSnapshotResolver,
        private IssueLinkedReferencesResolver $linkedReferencesResolver,
    ) {}

    /**
     * Build the get_linked_prs_or_commits tool for issue relationship discovery.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool
    {
        return Tool::as('get_linked_prs_or_commits')
            ->for('Resolve pull requests and commits linked to the current issue from timeline and conversation references.')
            ->using(function () use ($commandRun): string {
                $snapshot = $this->issueSnapshotResolver->resolve($commandRun, includeTimeline: true);
                if (! $snapshot instanceof \App\Services\Commands\ValueObjects\IssueSnapshot || isset($snapshot->issue['pull_request'])) {
                    return 'Issue context unavailable. This tool only applies to commands triggered from non-PR issues.';
                }

                $resolved = $this->linkedReferencesResolver->resolve(
                    issue: $snapshot->issue,
                    comments: $snapshot->comments,
                    timeline: $snapshot->timeline,
                );
                $pullRequests = $resolved['pull_requests'];
                $commits = $resolved['commits'];

                if ($pullRequests === [] && $commits === []) {
                    return 'No linked pull requests or commits were found for this issue.';
                }

                $sections = ['Linked references:'];

                if ($pullRequests !== []) {
                    $sections[] = "Pull requests:\n".implode("\n", array_map(
                        static fn (array $pr): string => sprintf(
                            '- #%d %s%s (source: %s)',
                            $pr['number'],
                            $pr['title'],
                            $pr['state'] !== '' ? ' ['.$pr['state'].']' : '',
                            $pr['source']
                        ),
                        $pullRequests
                    ));
                }

                if ($commits !== []) {
                    $sections[] = "Commits:\n".implode("\n", array_map(
                        static fn (array $commit): string => sprintf(
                            '- %s%s (source: %s)',
                            mb_substr($commit['sha'], 0, 12),
                            $commit['message'] !== '' ? ' - '.$commit['message'] : '',
                            $commit['source']
                        ),
                        $commits
                    ));
                }

                return implode("\n\n", $sections);
            });
    }
}
