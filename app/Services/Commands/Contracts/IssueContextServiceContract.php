<?php

declare(strict_types=1);

namespace App\Services\Commands\Contracts;

use App\Models\CommandRun;

/**
 * Contract for building issue context for command execution.
 */
interface IssueContextServiceContract
{
    /**
     * Build context string from issue data.
     *
     * Returns null if the command is associated with a pull request
     * or cannot be resolved to an issue.
     */
    public function buildContext(CommandRun $commandRun): ?string;
}
