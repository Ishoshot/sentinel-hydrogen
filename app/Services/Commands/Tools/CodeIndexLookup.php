<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

use App\Models\CodeIndex;
use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;

/**
 * Resolves code index entries with path rule enforcement.
 */
final readonly class CodeIndexLookup
{
    /**
     * Resolve a code index entry or return an error message.
     */
    public function find(CommandRun $commandRun, CommandPathRules $pathRules, string $filePath): CodeIndex|string
    {
        $repository = $commandRun->repository;
        if ($repository === null) {
            return 'Error: Repository not available for file lookup.';
        }

        if (! $pathRules->shouldIncludePath($filePath)) {
            return sprintf('Access denied: %s is excluded by repository path rules.', $filePath);
        }

        if ($pathRules->isSensitivePath($filePath)) {
            return sprintf('Access denied: %s is marked as sensitive.', $filePath);
        }

        $codeIndex = CodeIndex::where('repository_id', $repository->id)
            ->where('file_path', $filePath)
            ->first();

        if ($codeIndex === null) {
            return sprintf('File not found in index: %s. The file may not be indexed or the path may be incorrect.', $filePath);
        }

        return $codeIndex;
    }
}
