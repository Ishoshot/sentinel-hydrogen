<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Enums\Commands\CommandType;
use App\Jobs\Commands\ExecuteCommandRunJob;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\Commands\CommandPermissionService;
use App\Services\Commands\Parsers\CommandParser;
use App\Services\Commands\ValueObjects\ParsedCommand;
use Illuminate\Support\Facades\Log;

/**
 * Handles @sentinel commands from GitHub issue_comment payloads.
 */
final readonly class HandleIssueCommentCommand
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private CommandParser $commandParser,
        private CommandPermissionService $permissionService,
        private CreateCommandRun $createCommandRun,
        private TriggerReviewFromIssueComment $triggerReviewFromIssueComment,
        private PostIssueCommentMessage $postIssueCommentMessage,
    ) {}

    /**
     * Execute the issue comment command flow.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        // Only process 'created' action (new comments)
        $action = $payload['action'] ?? '';
        if ($action !== 'created') {
            return;
        }

        $commentBody = $payload['comment']['body'] ?? '';
        $commentId = $payload['comment']['id'] ?? 0;
        $senderLogin = $payload['sender']['login'] ?? '';
        $repositoryFullName = $payload['repository']['full_name'] ?? '';
        $installationId = $payload['installation']['id'] ?? 0;

        // Check if this is an issue or PR
        $issueNumber = $payload['issue']['number'] ?? null;
        $isPullRequest = isset($payload['issue']['pull_request']);

        $ctx = [
            'installation_id' => $installationId,
            'repository' => $repositoryFullName,
            'sender' => $senderLogin,
            'comment_id' => $commentId,
            'issue_number' => $issueNumber,
            'is_pull_request' => $isPullRequest,
        ];

        // Check if this is an @sentinel mention
        $parsed = $this->commandParser->parse($commentBody);

        if (! ($parsed instanceof ParsedCommand) || ! $parsed->wasFound()) {
            Log::debug('Issue comment does not contain @sentinel mention', $ctx);

            return;
        }

        $commandType = $parsed->commandType;
        if (! ($commandType instanceof CommandType)) {
            Log::warning('Could not determine command type from parsed mention', $ctx);

            return;
        }

        Log::info('Processing @sentinel command', array_merge($ctx, [
            'command_type' => $commandType->value,
            'query' => mb_substr($parsed->query ?? '', 0, 100),
        ]));

        // Check permissions
        $permissionResult = $this->permissionService->checkPermission($senderLogin, $repositoryFullName);

        if (! $permissionResult->allowed) {
            Log::warning('Command permission denied', array_merge($ctx, [
                'reason' => $permissionResult->code,
                'message' => $permissionResult->message,
            ]));

            if (is_int($issueNumber)) {
                $this->postIssueCommentMessage->postPermissionDenied(
                    installationId: $installationId,
                    repositoryFullName: $repositoryFullName,
                    issueNumber: $issueNumber,
                    message: $permissionResult->message ?? 'Permission denied',
                );
            }

            return;
        }

        // Ensure we have required data (already validated above)
        if (! $permissionResult->workspace instanceof Workspace || ! $permissionResult->repository instanceof Repository) {
            Log::error('Permission allowed but workspace/repository is null', $ctx);

            return;
        }

        // Handle @sentinel review command on PRs - triggers full automated review flow
        if ($commandType === CommandType::Review && $isPullRequest && $issueNumber !== null) {
            $this->triggerReviewFromIssueComment->handle(
                repository: $permissionResult->repository,
                pullRequestNumber: $issueNumber,
                senderLogin: $senderLogin,
                installationId: $installationId,
                context: $ctx,
            );

            return;
        }

        if ($commandType === CommandType::Review && ! $isPullRequest && $issueNumber !== null) {
            $this->postIssueCommentMessage->postReviewError(
                installationId: $installationId,
                repositoryFullName: $repositoryFullName,
                pullRequestNumber: $issueNumber,
                message: 'Manual reviews are only supported on pull requests. Use @sentinel explain or @sentinel analyze on issues.'
            );

            return;
        }

        // Handle other commands via the agent-based command flow
        $commandRun = $this->createCommandRun->handle(
            workspace: $permissionResult->workspace,
            repository: $permissionResult->repository,
            user: $permissionResult->user,
            commandType: $commandType,
            query: $parsed->query ?? '',
            githubCommentId: $commentId,
            issueNumber: $issueNumber,
            isPullRequest: $isPullRequest,
            contextHints: $parsed->contextHints,
        );

        if (! $commandRun->wasRecentlyCreated) {
            Log::info('Duplicate command webhook ignored', array_merge($ctx, [
                'command_run_id' => $commandRun->id,
            ]));

            return;
        }

        Log::info('Command run created', array_merge($ctx, [
            'command_run_id' => $commandRun->id,
        ]));

        // Dispatch job to execute the command
        ExecuteCommandRunJob::dispatch($commandRun->id);
    }
}
