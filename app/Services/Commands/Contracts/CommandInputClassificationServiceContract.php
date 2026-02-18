<?php

declare(strict_types=1);

namespace App\Services\Commands\Contracts;

use App\Models\CommandRun;
use App\Services\Commands\ValueObjects\CommandInputClassificationResult;

interface CommandInputClassificationServiceContract
{
    /**
     * Classify the command input before command execution.
     */
    public function classify(CommandRun $commandRun): CommandInputClassificationResult;
}
