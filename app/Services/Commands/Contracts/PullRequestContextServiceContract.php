<?php

declare(strict_types=1);

namespace App\Services\Commands\Contracts;

use App\Models\CommandRun;

/**
 * Contract for building pull request context for command execution.
 */
interface PullRequestContextServiceContract
{
    /**
     * Build context string from pull request data.
     *
     * Returns null if the command is not associated with a pull request.
     *
     * @return string|null The formatted PR context or null if not a PR
     */
    public function buildContext(CommandRun $commandRun): ?string;

    /**
     * Get pull request metadata for storing in CommandRun.
     *
     * @return array{
     *     pr_title: string,
     *     pr_additions: int,
     *     pr_deletions: int,
     *     pr_changed_files: int,
     *     pr_context_included: bool,
     * }|null
     */
    public function getMetadata(CommandRun $commandRun): ?array;
}
