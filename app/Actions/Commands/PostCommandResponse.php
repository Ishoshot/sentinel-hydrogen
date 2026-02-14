<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Actions\Commands\Support\PostingContextResolver;
use App\Actions\Commands\Support\ResponseMarkdownFormatter;
use App\Models\CommandRun;
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
        private ResponseMarkdownFormatter $formatter,
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

        $body = $this->formatter->formatSuccess($commandRun, $answer);

        try {
            $this->postComment($context, $body);

            Log::info('Posted command response to GitHub', [
                'command_run_id' => $commandRun->id,
                'issue_number' => $context['issue_number'],
            ]);
        } catch (Throwable $throwable) {
            Log::error('Failed to post command response to GitHub', [
                'command_run_id' => $commandRun->id,
                'issue_number' => $context['issue_number'],
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

        $body = $this->formatter->formatError($commandRun, $exception);

        try {
            $this->postComment($context, $body);
        } catch (Throwable $throwable) {
            Log::error('Failed to post error response to GitHub', [
                'command_run_id' => $commandRun->id,
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
}
