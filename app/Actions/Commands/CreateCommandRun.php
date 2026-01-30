<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Enums\Commands\CommandRunStatus;
use App\Enums\Commands\CommandType;
use App\Models\CommandRun;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Commands\ValueObjects\ContextHints;
use Illuminate\Database\QueryException;

/**
 * Creates a new command run record.
 */
final readonly class CreateCommandRun
{
    /**
     * Create a new command run.
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
        ?ContextHints $contextHints = null,
    ): CommandRun {
        $externalReference = 'github:comment:'.$githubCommentId;

        try {
            return CommandRun::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'external_reference' => $externalReference,
                ],
                [
                    'repository_id' => $repository->id,
                    'initiated_by_id' => $user?->id,
                    'github_comment_id' => $githubCommentId,
                    'issue_number' => $issueNumber,
                    'is_pull_request' => $isPullRequest,
                    'command_type' => $commandType,
                    'query' => $query,
                    'status' => CommandRunStatus::Queued,
                    'context_snapshot' => [
                        'context_hints' => $contextHints?->toArray(),
                    ],
                    'created_at' => now(),
                ]
            );
        } catch (QueryException $queryException) {
            $existing = CommandRun::query()
                ->where('workspace_id', $workspace->id)
                ->where('external_reference', $externalReference)
                ->first();

            if ($existing instanceof CommandRun) {
                return $existing;
            }

            throw $queryException;
        }
    }
}
