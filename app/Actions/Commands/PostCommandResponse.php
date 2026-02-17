<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Actions\Commands\Resolvers\PostingContextResolver;
use App\Models\CommandRun;
use App\Services\Commands\Formatters\ResponseMarkdownFormatter;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts command response to GitHub as a comment.
 */
final readonly class PostCommandResponse
{
    /**
     * Create a new PostCommandResponse instance.
     */
    public function __construct(
        private GitHubApiServiceContract $githubApi,
        private PostingContextResolver $contextResolver,
        private ResponseMarkdownFormatter $markdownFormatter,
    ) {}

    /**
     * Post a successful response to GitHub.
     */
    public function handle(CommandRun $commandRun, string $answer): void
    {
        $context = $this->contextResolver->resolve($commandRun);

        if ($context === null) {
            Log::warning('Cannot post response: missing repository, issue number, or installation', [
                'command_run_id' => $commandRun->id,
            ]);

            return;
        }

        $body = $this->markdownFormatter->formatSuccess($commandRun, $answer);
        $ackCommentId = $this->ackCommentId($commandRun);

        try {
            if ($ackCommentId !== null) {
                $this->updateComment($context, $ackCommentId, $body);
            } else {
                $this->postComment($context, $body);
            }

            Log::info('Posted command response to GitHub', [
                'command_run_id' => $commandRun->id,
                'issue_number' => $context['issue_number'],
                'updated_ack_comment_id' => $ackCommentId,
            ]);
        } catch (Throwable $throwable) {
            Log::error('Failed to post command response to GitHub', [
                'command_run_id' => $commandRun->id,
                'issue_number' => $context['issue_number'],
                'updated_ack_comment_id' => $ackCommentId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Post an error response to GitHub.
     */
    public function handleError(CommandRun $commandRun, Throwable $exception): void
    {
        $context = $this->contextResolver->resolve($commandRun);

        if ($context === null) {
            return;
        }

        $body = $this->markdownFormatter->formatError($commandRun, $exception);
        $ackCommentId = $this->ackCommentId($commandRun);

        try {
            if ($ackCommentId !== null) {
                $this->updateComment($context, $ackCommentId, $body);
            } else {
                $this->postComment($context, $body);
            }
        } catch (Throwable $throwable) {
            Log::error('Failed to post error response to GitHub', [
                'command_run_id' => $commandRun->id,
                'updated_ack_comment_id' => $ackCommentId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Post a comment to GitHub.
     *
     * @param  array{installation_id: int, owner: string, repo: string, issue_number: int}  $context
     */
    private function postComment(array $context, string $body): void
    {
        $this->githubApi->createIssueComment(
            installationId: $context['installation_id'],
            owner: $context['owner'],
            repo: $context['repo'],
            number: $context['issue_number'],
            body: $body
        );
    }

    /**
     * Update an existing GitHub comment.
     *
     * @param  array{installation_id: int, owner: string, repo: string, issue_number: int}  $context
     */
    private function updateComment(array $context, int $commentId, string $body): void
    {
        $this->githubApi->updateIssueComment(
            installationId: $context['installation_id'],
            owner: $context['owner'],
            repo: $context['repo'],
            commentId: $commentId,
            body: $body
        );
    }

    /**
     * Resolve the acknowledgment comment ID for a command run.
     */
    private function ackCommentId(CommandRun $commandRun): ?int
    {
        $metadata = $commandRun->metadata;

        if (! is_array($metadata)) {
            return null;
        }

        if (! isset($metadata['github_ack_comment_id']) || ! is_numeric($metadata['github_ack_comment_id'])) {
            return null;
        }

        return (int) $metadata['github_ack_comment_id'];
    }
}
