<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Contracts\CommandToolBuilder;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

final readonly class GetFileStructureTool implements CommandToolBuilder
{
    /**
     * Create a new GetFileStructureTool instance.
     */
    public function __construct(private CodeIndexLookup $lookup) {}

    /**
     * Build the get_file_structure tool to get AST structure of a file.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool
    {
        return Tool::as('get_file_structure')
            ->for('Get the structural analysis (classes, methods, functions) of a file. Use this to understand the organization of a file without reading all content.')
            ->withStringParameter('file_path', 'The path to the file to analyze')
            ->using(function (string $filePath) use ($commandRun, $pathRules): string {
                $codeIndex = $this->lookup->find($commandRun, $pathRules, $filePath);
                if (is_string($codeIndex)) {
                    return $codeIndex;
                }

                $structure = $codeIndex->structure;

                if ($structure === null || $structure === []) {
                    return sprintf('No structural analysis available for: %s. This file type may not support structural analysis.', $filePath);
                }

                return sprintf(
                    "File: %s\nType: %s\n\nStructure:\n%s",
                    $filePath,
                    $codeIndex->file_type,
                    json_encode($structure, JSON_PRETTY_PRINT)
                );
            });
    }
}
