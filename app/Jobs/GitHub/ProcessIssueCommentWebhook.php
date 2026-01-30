<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Actions\Commands\CreateCommandRun;
use App\Actions\Reviews\TriggerManualReview;
use App\Enums\Commands\CommandType;
use App\Enums\Queue\Queue;
use App\Jobs\Commands\ExecuteCommandRunJob;
use App\Services\Commands\CommandPermissionService;
use App\Services\Commands\Parsers\CommandParser;
use App\Services\Commands\ValueObjects\ParsedCommand;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes issue_comment webhooks from GitHub.
 *
 * Handles @sentinel mentions in issue and PR comments.
 */
final class ProcessIssueCommentWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload
    ) {
        $this->onQueue(Queue::Webhooks->value);
    }

    /**
     * Execute the job.
     */
    public function handle(
        CommandParser $commandParser,
        CommandPermissionService $permissionService,
        CreateCommandRun $createCommandRun,
        TriggerManualReview $triggerManualReview,
        GitHubApiServiceContract $githubApi
    ): void {
        // Only process 'created' action (new comments)
        $action = $this->payload['action'] ?? '';
        if ($action !== 'created') {
            return;
        }

        $commentBody = $this->payload['comment']['body'] ?? '';
        $commentId = $this->payload['comment']['id'] ?? 0;
        $senderLogin = $this->payload['sender']['login'] ?? '';
        $repositoryFullName = $this->payload['repository']['full_name'] ?? '';
        $installationId = $this->payload['installation']['id'] ?? 0;

        // Check if this is an issue or PR
        $issueNumber = $this->payload['issue']['number'] ?? null;
        $isPullRequest = isset($this->payload['issue']['pull_request']);

        $ctx = [
            'installation_id' => $installationId,
            'repository' => $repositoryFullName,
            'sender' => $senderLogin,
            'comment_id' => $commentId,
            'issue_number' => $issueNumber,
            'is_pull_request' => $isPullRequest,
        ];

        // Check if this is an @sentinel mention
        $parsed = $commandParser->parse($commentBody);

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
        $permissionResult = $permissionService->checkPermission($senderLogin, $repositoryFullName);

        if (! $permissionResult->allowed) {
            Log::warning('Command permission denied', array_merge($ctx, [
                'reason' => $permissionResult->code,
                'message' => $permissionResult->message,
            ]));

            // Post a reply with the error message
            $this->postPermissionDeniedComment(
                $githubApi,
                $installationId,
                $repositoryFullName,
                $issueNumber,
                $permissionResult->message ?? 'Permission denied'
            );

            return;
        }

        // Ensure we have required data (already validated above)
        if (! $permissionResult->workspace instanceof \App\Models\Workspace || ! $permissionResult->repository instanceof \App\Models\Repository) {
            Log::error('Permission allowed but workspace/repository is null', $ctx);

            return;
        }

        // Handle @sentinel review command on PRs - triggers full automated review flow
        if ($commandType === CommandType::Review && $isPullRequest && $issueNumber !== null) {
            $this->handleManualReviewTrigger(
                $triggerManualReview,
                $githubApi,
                $permissionResult->repository,
                $issueNumber,
                $senderLogin,
                $installationId,
                $ctx
            );

            return;
        }

        if ($commandType === CommandType::Review && ! $isPullRequest && $issueNumber !== null) {
            $this->postReviewErrorComment(
                $githubApi,
                $installationId,
                $repositoryFullName,
                $issueNumber,
                'Manual reviews are only supported on pull requests. Use @sentinel explain or @sentinel analyze on issues.'
            );

            return;
        }

        // Handle other commands via the agent-based command flow
        $commandRun = $createCommandRun->handle(
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

    /**
     * Handle the @sentinel review command by triggering a full automated review.
     *
     * @param  array<string, mixed>  $ctx
     */
    private function handleManualReviewTrigger(
        TriggerManualReview $triggerManualReview,
        GitHubApiServiceContract $githubApi,
        \App\Models\Repository $repository,
        int $prNumber,
        string $senderLogin,
        int $installationId,
        array $ctx
    ): void {
        Log::info('Triggering manual review from @sentinel review command', $ctx);

        $result = $triggerManualReview->handle(
            repository: $repository,
            prNumber: $prNumber,
            senderLogin: $senderLogin,
        );

        // If the review failed to start (not just skipped), post an error comment
        if (! $result['success'] && $result['run'] === null) {
            $this->postReviewErrorComment(
                $githubApi,
                $installationId,
                $repository->full_name,
                $prNumber,
                $result['message']
            );
        }

        Log::info('Manual review trigger completed', array_merge($ctx, [
            'success' => $result['success'],
            'run_id' => $result['run']?->id,
            'message' => $result['message'],
        ]));
    }

    /**
     * Post a comment explaining why the review could not be triggered.
     */
    private function postReviewErrorComment(
        GitHubApiServiceContract $githubApi,
        int $installationId,
        string $repositoryFullName,
        int $prNumber,
        string $message
    ): void {
        $parts = explode('/', $repositoryFullName, 2);
        if (count($parts) !== 2) {
            Log::warning('Invalid repository full name format', [
                'repository' => $repositoryFullName,
            ]);

            return;
        }

        [$owner, $repo] = $parts;

        try {
            $githubApi->createIssueComment(
                installationId: $installationId,
                owner: $owner,
                repo: $repo,
                number: $prNumber,
                body: '**Sentinel**: '.$message
            );
        } catch (Throwable $throwable) {
            Log::warning('Failed to post review error comment', [
                'repository' => $repositoryFullName,
                'pr_number' => $prNumber,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Post a comment explaining why the command was denied.
     */
    private function postPermissionDeniedComment(
        GitHubApiServiceContract $githubApi,
        int $installationId,
        string $repositoryFullName,
        ?int $issueNumber,
        string $message
    ): void {
        if ($issueNumber === null) {
            return;
        }

        $parts = explode('/', $repositoryFullName, 2);
        if (count($parts) !== 2) {
            Log::warning('Invalid repository full name format', [
                'repository' => $repositoryFullName,
            ]);

            return;
        }

        [$owner, $repo] = $parts;

        try {
            $githubApi->createIssueComment(
                installationId: $installationId,
                owner: $owner,
                repo: $repo,
                number: $issueNumber,
                body: '**Sentinel**: '.$message
            );
        } catch (Throwable $throwable) {
            Log::warning('Failed to post permission denied comment', [
                'repository' => $repositoryFullName,
                'issue_number' => $issueNumber,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
