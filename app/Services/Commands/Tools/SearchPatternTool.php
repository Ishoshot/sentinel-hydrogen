<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CodeIndex;
use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Contracts\CommandToolBuilder;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

final readonly class SearchPatternTool implements CommandToolBuilder
{
    /**
     * Build the search_pattern tool for grep-like exact pattern matching.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool
    {
        $repository = $commandRun->repository;

        return Tool::as('search_pattern')
            ->for('Search for exact text patterns or regex in the codebase. Use this for precise pattern matching like finding specific function calls, variable names, or code patterns. More precise than search_code.')
            ->withStringParameter('pattern', 'The exact text or regex pattern to search for (e.g., "function authenticate", "->validate(", "class.*Controller")')
            ->withStringParameter('file_type', 'Optional: filter by file type (e.g., "php", "js", "ts")')
            ->withNumberParameter('limit', 'Maximum number of results to return (default: 20, max: 50)')
            ->using(function (string $pattern, ?string $fileType = null, ?int $limit = null) use ($repository, $pathRules): string {
                if ($repository === null) {
                    return 'Error: Repository not available for search.';
                }

                $requestedLimit = min($limit ?? 20, 50);
                $searchLimit = min($requestedLimit * 3, 150);

                $query = CodeIndex::where('repository_id', $repository->id);

                if ($fileType !== null && $fileType !== '') {
                    $query->where('file_type', $fileType);
                }

                $query->where('content', 'LIKE', '%'.$pattern.'%');

                $results = $query->select(['file_path', 'file_type', 'content'])
                    ->limit($searchLimit)
                    ->get();

                $filtered = $results->filter(fn (CodeIndex $index): bool => $pathRules->shouldIncludePath($index->file_path))
                    ->values();
                $filtered = $filtered->take($requestedLimit);

                if ($filtered->isEmpty()) {
                    return 'No matches found for pattern: '.$pattern;
                }

                $formatted = $filtered->map(function (CodeIndex $index, int $idx) use ($pattern, $pathRules): string {
                    $filePath = $index->file_path;
                    if ($pathRules->isSensitivePath($filePath)) {
                        return sprintf(
                            "[%d] %s (%s)\n[REDACTED - sensitive file]",
                            $idx + 1,
                            $filePath,
                            $index->file_type
                        );
                    }

                    $matches = [];
                    $lines = explode("\n", $index->content);

                    foreach ($lines as $lineNum => $line) {
                        if (mb_stripos($line, $pattern) !== false) {
                            $matches[] = sprintf('  %4d | %s', $lineNum + 1, mb_trim($pathRules->redact($line)));
                            if (count($matches) >= 5) {
                                $matches[] = '  ... (more matches in this file)';
                                break;
                            }
                        }
                    }

                    return sprintf(
                        "[%d] %s (%s)\n%s",
                        $idx + 1,
                        $index->file_path,
                        $index->file_type,
                        implode("\n", $matches)
                    );
                })->join("\n\n");

                return 'Found matches in '.$filtered->count()." files:\n\n".$formatted;
            });
    }
}
