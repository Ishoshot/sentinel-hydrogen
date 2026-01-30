<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CommandRun;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Contracts\CommandToolBuilder;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

final readonly class FindSymbolTool implements CommandToolBuilder
{
    /**
     * Create a new FindSymbolTool instance.
     */
    public function __construct(
        private CodeSearchServiceContract $searchService,
        private ToolResultFormatter $formatter,
    ) {}

    /**
     * Build the find_symbol tool to find code by symbol name.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool
    {
        $repository = $commandRun->repository;

        return Tool::as('find_symbol')
            ->for('Find code definitions by symbol name (class, method, function, trait, interface). Use this to locate where something is defined.')
            ->withStringParameter('symbol_name', 'The name of the symbol to find (e.g., "UserController", "authenticate", "handleRequest")')
            ->withNumberParameter('limit', 'Maximum number of results (default: 5, max: 10)')
            ->using(function (string $symbolName, ?int $limit = null) use ($repository, $pathRules): string {
                if ($repository === null) {
                    return 'Error: Repository not available for search.';
                }

                $limit = min($limit ?? 5, 10);

                $results = $this->searchService->findSymbol($repository, $symbolName, $limit * 3);
                $filtered = array_values(array_filter(
                    $results,
                    fn (array $result): bool => $pathRules->shouldIncludePath($result['file_path'])
                ));
                $filtered = array_slice($filtered, 0, $limit);

                if ($filtered === []) {
                    return 'No symbol found matching: '.$symbolName;
                }

                $formatted = array_map(function (array $result, int $index) use ($pathRules): string {
                    $filePath = $result['file_path'];
                    $symbol = $result['symbol_name'];
                    $chunkType = $result['chunk_type'];
                    $content = $pathRules->sanitizeContentForPath($filePath, $result['content']);

                    $content = $this->formatter->truncate($content, 400);

                    return sprintf(
                        "[%d] %s (%s) in %s\n%s",
                        $index + 1,
                        $symbol,
                        $chunkType,
                        $filePath,
                        $content
                    );
                }, $filtered, array_keys($filtered));

                return 'Found '.count($filtered)." symbols:\n\n".implode("\n\n---\n\n", $formatted);
            });
    }
}
