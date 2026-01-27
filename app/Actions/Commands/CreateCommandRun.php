<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Enums\CommandRunStatus;
use App\Enums\CommandType;
use App\Models\CommandRun;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;

/**
 * Creates a new command run record.
 */
final readonly class CreateCommandRun
{
    /**
     * Create a new command run.
     *
     * @param  array{files: array<string>, symbols: array<string>, lines: array<array{start: int, end: int|null}>}|null  $contextHints
     */
    public function handle(
        Workspace $workspace,
        Repository $repository,
        ?User $user,
        CommandType $commandType,
        string $query,
        int $githubCommentId,
        ?int $issueNumber,
        bool $isPullRequest,
        ?array $contextHints = null,
    ): CommandRun {
        return CommandRun::create([
            'workspace_id' => $workspace->id,
            'repository_id' => $repository->id,
            'initiated_by_id' => $user?->id,
            'external_reference' => 'github:comment:'.$githubCommentId,
            'github_comment_id' => $githubCommentId,
            'issue_number' => $issueNumber,
            'is_pull_request' => $isPullRequest,
            'command_type' => $commandType,
            'query' => $query,
            'status' => CommandRunStatus::Queued,
            'context_snapshot' => [
                'context_hints' => $contextHints,
            ],
            'created_at' => now(),
        ]);
    }
}
