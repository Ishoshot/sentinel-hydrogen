<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Actions\Commands\Resolvers\PostingContextResolver;
use App\Models\CommandRun;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts an initial acknowledgment comment for a command run.
 */
final readonly class PostCommandAcknowledgment
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $githubApi,
        private PostingContextResolver $contextResolver,
    ) {}

    /**
     * Post the acknowledgment comment and persist its GitHub comment ID.
     *
     * @return int|null The acknowledgment comment ID when created.
     */
    public function handle(CommandRun $commandRun): ?int
    {
        $context = $this->contextResolver->resolve($commandRun);

        if ($context === null) {
            Log::warning('Cannot post command acknowledgment: missing posting context', [
                'command_run_id' => $commandRun->id,
            ]);

            return null;
        }

        try {
            $response = $this->githubApi->createIssueComment(
                installationId: $context['installation_id'],
                owner: $context['owner'],
                repo: $context['repo'],
                number: $context['issue_number'],
                body: $this->message($commandRun),
            );
        } catch (Throwable $throwable) {
            Log::warning('Failed to post command acknowledgment comment', [
                'command_run_id' => $commandRun->id,
                'issue_number' => $context['issue_number'],
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }

        $commentId = isset($response['id']) && is_numeric($response['id'])
            ? (int) $response['id']
            : null;

        if ($commentId === null) {
            Log::warning('Command acknowledgment response missing comment ID', [
                'command_run_id' => $commandRun->id,
                'issue_number' => $context['issue_number'],
            ]);

            return null;
        }

        $metadata = $commandRun->metadata;
        if (! is_array($metadata)) {
            $metadata = [];
        }

        $metadata['github_ack_comment_id'] = $commentId;
        $commandRun->update(['metadata' => $metadata]);

        return $commentId;
    }

    /**
     * Build the initial acknowledgment comment body.
     */
    private function message(CommandRun $commandRun): string
    {
        $commandType = $commandRun->command_type->description();

        return <<<MD
        **Sentinel**: Starting {$commandType}...

        I'll analyze your request and post the response shortly.
        MD;
    }
}
