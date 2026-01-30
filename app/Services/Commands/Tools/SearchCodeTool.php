<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CommandRun;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Contracts\CommandToolBuilder;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

final readonly class SearchCodeTool implements CommandToolBuilder
{
    /**
     * Create a new SearchCodeTool instance.
     */
    public function __construct(
        private CodeSearchServiceContract $searchService,
        private ToolResultFormatter $formatter,
    ) {}

    /**
     * Build the search_code tool for hybrid code search.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool
    {
        $repository = $commandRun->repository;

        return Tool::as('search_code')
            ->for('Search the codebase using hybrid keyword and semantic search. Use this to find relevant code files, classes, methods, or functions.')
            ->withStringParameter('query', 'The search query - can be natural language describing what you are looking for, or specific code patterns/names')
            ->withNumberParameter('limit', 'Maximum number of results to return (default: 10, max: 20)')
            ->using(function (string $query, ?int $limit = null) use ($repository, $pathRules): string {
                if ($repository === null) {
                    return 'Error: Repository not available for search.';
                }

                $requestedLimit = min($limit ?? 10, 20);
                $searchLimit = min($requestedLimit * 3, 50);

                $results = $this->searchService->search($repository, $query, $searchLimit);
                $filtered = array_values(array_filter(
                    $results,
                    fn (array $result): bool => $pathRules->shouldIncludePath($result['file_path'])
                ));
                $filtered = array_slice($filtered, 0, $requestedLimit);

                if ($filtered === []) {
                    return 'No results found for the given query.';
                }

                $formatted = array_map(function (array $result, int $index) use ($pathRules): string {
                    $matchType = $result['match_type'];
                    $score = number_format($result['score'], 2);
                    $filePath = $result['file_path'];
                    $content = $pathRules->sanitizeContentForPath($filePath, $result['content']);

                    $content = $this->formatter->truncate($content, 300);

                    return sprintf(
                        "[%d] %s (score: %s, type: %s)\n%s",
                        $index + 1,
                        $filePath,
                        $score,
                        $matchType,
                        $content
                    );
                }, $filtered, array_keys($filtered));

                return 'Found '.count($formatted)." results:\n\n".implode("\n\n---\n\n", $formatted);
            });
    }
}
