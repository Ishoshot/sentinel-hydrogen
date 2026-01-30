<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Contracts\CommandToolBuilder;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

final readonly class ReadFileTool implements CommandToolBuilder
{
    /**
     * Create a new ReadFileTool instance.
     */
    public function __construct(private CodeIndexLookup $lookup) {}

    /**
     * Build the read_file tool to read indexed file content.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool
    {
        return Tool::as('read_file')
            ->for('Read the full content of a file from the indexed codebase. Use this after search_code to get complete file contents.')
            ->withStringParameter('file_path', 'The path to the file to read (as returned by search_code)')
            ->using(function (string $filePath) use ($commandRun, $pathRules): string {
                $codeIndex = $this->lookup->find($commandRun, $pathRules, $filePath);
                if (is_string($codeIndex)) {
                    return $codeIndex;
                }

                $content = $pathRules->sanitizeContentForPath($filePath, $codeIndex->content);
                if ($content === '[REDACTED - sensitive file]') {
                    return sprintf('Access denied: %s is marked as sensitive.', $filePath);
                }

                $lines = mb_substr_count($content, "\n") + 1;
                $fileType = $codeIndex->file_type;

                $numberedLines = [];
                foreach (explode("\n", $content) as $lineNum => $line) {
                    $numberedLines[] = sprintf('%4d | %s', $lineNum + 1, $line);
                }

                return sprintf(
                    "File: %s\nType: %s\nLines: %d\n\n%s",
                    $filePath,
                    $fileType,
                    $lines,
                    implode("\n", $numberedLines)
                );
            });
    }
}
