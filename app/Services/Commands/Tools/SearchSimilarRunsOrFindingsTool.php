<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Contracts\CommandToolBuilder;
use App\Services\Commands\Resolvers\SimilarRunsAndFindingsResolver;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

final readonly class SearchSimilarRunsOrFindingsTool implements CommandToolBuilder
{
    /**
     * Create a new SearchSimilarRunsOrFindingsTool instance.
     */
    public function __construct(private SimilarRunsAndFindingsResolver $similarRunsAndFindingsResolver) {}

    /**
     * Build the search_similar_runs_or_findings tool for historical context.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool
    {
        return Tool::as('search_similar_runs_or_findings')
            ->for('Search historical review runs and findings in this repository/workspace that are similar to the issue context.')
            ->withStringParameter('query', 'Optional query override. Defaults to the command query.')
            ->withNumberParameter('run_limit', 'Maximum runs to return (default: 5, max: 20)')
            ->withNumberParameter('finding_limit', 'Maximum findings to return (default: 8, max: 30)')
            ->using(function (?string $query = null, ?int $runLimit = null, ?int $findingLimit = null) use ($commandRun): string {
                $resolved = $this->similarRunsAndFindingsResolver->resolve(
                    commandRun: $commandRun,
                    query: $query,
                    runLimit: $runLimit ?? 5,
                    findingLimit: $findingLimit ?? 8,
                );

                $runs = $resolved['runs'];
                $findings = $resolved['findings'];

                if ($runs === [] && $findings === []) {
                    return 'No similar historical runs or findings were found.';
                }

                $sections = ['Similar historical context:'];

                if ($runs !== []) {
                    $sections[] = "Runs:\n".implode("\n", array_map(
                        static fn (array $run): string => sprintf(
                            '- Run #%d [%s] PR #%s %s | findings: %d | at: %s',
                            $run['run_id'],
                            $run['status'],
                            $run['pr_number'] ?? '-',
                            $run['pr_title'],
                            $run['findings_count'],
                            $run['created_at'] ?? 'unknown'
                        ),
                        $runs
                    ));
                }

                if ($findings !== []) {
                    $sections[] = "Findings:\n".implode("\n", array_map(
                        static fn (array $finding): string => sprintf(
                            '- [%s/%s] %s (PR #%s %s, file: %s, confidence: %s)',
                            $finding['severity'],
                            $finding['category'],
                            $finding['title'],
                            $finding['pr_number'] ?? '-',
                            $finding['pr_title'],
                            $finding['file_path'] ?? 'n/a',
                            $finding['confidence'] !== null ? number_format($finding['confidence'], 2) : 'n/a'
                        ),
                        $findings
                    ));
                }

                return implode("\n\n", $sections);
            });
    }
}
