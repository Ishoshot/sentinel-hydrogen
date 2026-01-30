<?php

declare(strict_types=1);

namespace App\Services\Commands\Contracts;

use App\Models\CommandRun;
use App\Services\Commands\CommandPathRules;
use Prism\Prism\Tool as PrismTool;

/**
 * Contract for command tool builders.
 *
 * Tool builders create Prism tools that can be used by the command
 * agent to interact with the codebase during command execution.
 */
interface CommandToolBuilder
{
    /**
     * Build a Prism tool for use in command execution.
     */
    public function build(CommandRun $commandRun, CommandPathRules $pathRules): PrismTool;
}
