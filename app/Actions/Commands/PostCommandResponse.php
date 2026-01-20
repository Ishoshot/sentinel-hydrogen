<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Models\CommandRun;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts command response to GitHub as a comment.
 */
final readonly class PostCommandResponse
{
    private const int MAX_RESPONSE_LENGTH = 60000;

    /**
     * Create a new PostCommandResponse instance.
     */
    public function __construct(
        private GitHubApiServiceContract $githubApi,
    ) {}

    /**
     * Post a successful response to GitHub.
     */
    public function handle(CommandRun $commandRun, string $answer): void
    {
        $context = $this->resolvePostingContext($commandRun);

        if ($context === null) {
            Log::warning('Cannot post response: missing repository, issue number, or installation', [
                'command_run_id' => $commandRun->id,
            ]);

            return;
        }

        $body = $this->formatResponse($commandRun, $answer);

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
        $context = $this->resolvePostingContext($commandRun);

        if ($context === null) {
            return;
        }

        $body = $this->formatErrorResponse($commandRun, $exception);

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
     * Resolve the context needed to post a comment.
     *
     * @return array{installation_id: int, owner: string, repo: string, issue_number: int}|null
     */
    private function resolvePostingContext(CommandRun $commandRun): ?array
    {
        $repository = $commandRun->repository;
        $issueNumber = $commandRun->issue_number;

        if ($repository === null || $issueNumber === null) {
            return null;
        }

        $installation = $repository->installation;
        if ($installation === null) {
            return null;
        }

        return [
            'installation_id' => $installation->installation_id,
            'owner' => $repository->owner,
            'repo' => $repository->name,
            'issue_number' => $issueNumber,
        ];
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
     * Format the response body for GitHub.
     */
    private function formatResponse(CommandRun $commandRun, string $answer): string
    {
        $commandType = $commandRun->command_type->description();
        $metrics = $commandRun->metrics ?? [];

        // Truncate answer if too long
        if (mb_strlen($answer) > self::MAX_RESPONSE_LENGTH) {
            $answer = mb_substr($answer, 0, self::MAX_RESPONSE_LENGTH)
                ."\n\n---\n*Response truncated due to length.*";
        }

        $header = "### Sentinel - {$commandType}\n\n";

        // Build footer with metrics
        $footer = $this->buildFooter($metrics);

        return $header.$answer.$footer;
    }

    /**
     * Format an error response body for GitHub.
     */
    private function formatErrorResponse(CommandRun $commandRun, Throwable $exception): string
    {
        $commandType = $commandRun->command_type->description();

        // Sanitize error message (remove sensitive info)
        $errorMessage = $this->sanitizeErrorMessage($exception->getMessage());

        return <<<MD
### Sentinel - {$commandType}

I encountered an error while processing your request:

> {$errorMessage}

Please try again later. If the problem persists, contact support.

---
*Powered by [Sentinel](https://sentinelapp.dev)*
MD;
    }

    /**
     * Build the footer with metrics.
     *
     * @param  array<string, mixed>  $metrics
     */
    private function buildFooter(array $metrics): string
    {
        $parts = [];

        if (isset($metrics['model'])) {
            $parts[] = sprintf('Model: `%s`', $metrics['model']);
        }

        if (isset($metrics['duration_ms']) && is_numeric($metrics['duration_ms'])) {
            $duration = number_format((float) $metrics['duration_ms'] / 1000, 1);
            $parts[] = sprintf('Time: %ss', $duration);
        }

        if ($parts === []) {
            return "\n\n---\n*Powered by [Sentinel](https://sentinelapp.dev)*";
        }

        return "\n\n---\n<sub>".implode(' | ', $parts).' | Powered by [Sentinel](https://sentinelapp.dev)</sub>';
    }

    /**
     * Sanitize error message to remove sensitive information.
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Remove API keys, tokens, etc.
        $message = preg_replace('/sk-[a-zA-Z0-9]{20,}/', '[REDACTED]', $message) ?? $message;
        $message = preg_replace('/Bearer [a-zA-Z0-9\-_.]+/', 'Bearer [REDACTED]', $message) ?? $message;

        // Truncate long messages
        if (mb_strlen($message) > 200) {
            return mb_substr($message, 0, 200).'...';
        }

        return $message;
    }
}
