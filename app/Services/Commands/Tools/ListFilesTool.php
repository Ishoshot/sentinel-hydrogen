<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CodeIndex;
use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Contracts\CommandToolBuilder;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

final readonly class ListFilesTool implements CommandToolBuilder
{
    /**
     * Build the list_files tool to list files in the repository.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool
    {
        $repository = $commandRun->repository;

        return Tool::as('list_files')
            ->for('List files in the indexed repository. Use this to explore the codebase structure, find files by path pattern, or filter by file type.')
            ->withStringParameter('path_pattern', 'Optional: filter files by path pattern (e.g., "app/Models", "tests/", "Controller")')
            ->withStringParameter('file_type', 'Optional: filter by file type (e.g., "php", "js", "vue")')
            ->withNumberParameter('limit', 'Maximum number of files to return (default: 30, max: 100)')
            ->using(function (?string $pathPattern = null, ?string $fileType = null, ?int $limit = null) use ($repository, $pathRules): string {
                if ($repository === null) {
                    return 'Error: Repository not available for listing files.';
                }

                $requestedLimit = min($limit ?? 30, 100);
                $searchLimit = min($requestedLimit * 3, 300);

                $query = CodeIndex::where('repository_id', $repository->id);

                if ($pathPattern !== null && $pathPattern !== '') {
                    $query->where('file_path', 'LIKE', '%'.$pathPattern.'%');
                }

                if ($fileType !== null && $fileType !== '') {
                    $query->where('file_type', $fileType);
                }

                $files = $query->select(['file_path', 'file_type'])
                    ->orderBy('file_path')
                    ->limit($searchLimit)
                    ->get();

                $filtered = $files->filter(fn (CodeIndex $file): bool => $pathRules->shouldIncludePath($file->file_path))
                    ->values()
                    ->take($requestedLimit);

                if ($filtered->isEmpty()) {
                    $filters = [];
                    if ($pathPattern) {
                        $filters[] = 'path pattern: '.$pathPattern;
                    }

                    if ($fileType) {
                        $filters[] = 'file type: '.$fileType;
                    }

                    return 'No files found'.($filters !== [] ? ' matching '.implode(', ', $filters) : '').'.';
                }

                $grouped = $filtered->groupBy(fn (CodeIndex $file): string => dirname($file->file_path));

                $output = [];
                foreach ($grouped as $directory => $directoryFiles) {
                    $output[] = $directory.'/';
                    foreach ($directoryFiles as $file) {
                        $suffix = $pathRules->isSensitivePath($file->file_path) ? ' (sensitive)' : '';
                        $output[] = '  - '.basename((string) $file->file_path).sprintf(' (%s)%s', $file->file_type, $suffix);
                    }
                }

                $header = sprintf('Showing %s files', $filtered->count());
                if ($filtered->count() >= $requestedLimit) {
                    $header .= ' (use path_pattern or file_type to filter)';
                }

                return $header." (filtered by repository rules):\n\n".implode("\n", $output);
            });
    }
}
