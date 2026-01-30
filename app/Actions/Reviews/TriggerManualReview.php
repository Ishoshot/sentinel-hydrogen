<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\Queue\Queue;
use App\Enums\Reviews\RunStatus;
use App\Jobs\Reviews\ExecuteReviewRun;
use App\Models\Repository;
use App\Models\Run;
use App\Models\User;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\Queue\QueueResolver;
use App\Services\Queue\ValueObjects\JobContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Triggers a manual code review for a pull request.
 *
 * This action is called when a user comments @sentinel review on a PR,
 * and simulates the same review flow that would occur from a webhook.
 */
final readonly class TriggerManualReview
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $githubApi,
        private CreatePullRequestRun $createPullRequestRun,
        private QueueResolver $queueResolver,
    ) {}

    /**
     * Trigger a manual review for a pull request.
     *
     * @return array{success: bool, run: Run|null, message: string}
     */
    public function handle(
        Repository $repository,
        int $prNumber,
        string $senderLogin,
    ): array {
        $installation = $repository->installation;

        if ($installation === null) {
            return [
                'success' => false,
                'run' => null,
                'message' => 'Repository installation not found.',
            ];
        }

        $ctx = [
            'repository_id' => $repository->id,
            'pr_number' => $prNumber,
            'sender' => $senderLogin,
        ];

        Log::info('Triggering manual review', $ctx);

        // Check if auto-review is enabled
        if (! $repository->hasAutoReviewEnabled()) {
            Log::info('Manual review requested but auto-review disabled', $ctx);

            return [
                'success' => false,
                'run' => null,
                'message' => 'Code reviews are disabled for this repository. Enable auto-review in repository settings to use this feature.',
            ];
        }

        // Fetch PR data from GitHub API
        try {
            $prData = $this->githubApi->getPullRequest(
                installationId: $installation->installation_id,
                owner: $repository->owner,
                repo: $repository->name,
                number: $prNumber
            );
        } catch (Throwable $throwable) {
            Log::warning('Failed to fetch PR data for manual review', array_merge($ctx, [
                'error' => $throwable->getMessage(),
            ]));

            return [
                'success' => false,
                'run' => null,
                'message' => 'Unable to fetch pull request details from GitHub.',
            ];
        }

        // Transform GitHub API response to webhook payload format
        $payload = $this->buildPayload($repository, $installation->installation_id, $prData, $senderLogin);

        // Post acknowledgment comment
        $greetingCommentId = $this->postAcknowledgmentComment(
            $installation->installation_id,
            $repository->owner,
            $repository->name,
            $prNumber
        );

        // Create the run using the existing action
        $run = $this->createPullRequestRun->handle($repository, $payload, $greetingCommentId);

        // Check if run was skipped (e.g., due to plan limits)
        if ($run->status === RunStatus::Skipped) {
            Log::info('Manual review skipped', array_merge($ctx, [
                'run_id' => $run->id,
                'reason' => $run->metadata['skip_reason'] ?? 'unknown',
            ]));

            return [
                'success' => false,
                'run' => $run,
                'message' => (string) ($run->metadata['skip_reason'] ?? 'Review was skipped.'),
            ];
        }

        // Determine the queue based on workspace tier
        $workspace = $repository->workspace;
        $queue = $workspace !== null
            ? $this->queueResolver->resolve(JobContext::forWorkspace(ExecuteReviewRun::class, $workspace, true, 'high'))->queue
            : Queue::ReviewsDefault;

        // Dispatch the review job
        ExecuteReviewRun::dispatch($run->id, $queue);

        Log::info('Manual review queued', array_merge($ctx, [
            'run_id' => $run->id,
            'queue' => $queue->value,
        ]));

        return [
            'success' => true,
            'run' => $run,
            'message' => "Review started. I'll analyze the changes and post my findings shortly.",
        ];
    }

    /**
     * Build the payload format expected by CreatePullRequestRun.
     *
     * @param  array<string, mixed>  $prData  GitHub API pull request response
     * @return array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}
     */
    private function buildPayload(Repository $repository, int $installationId, array $prData, string $senderLogin): array
    {
        return [
            'action' => 'manual_trigger',
            'installation_id' => $installationId,
            'repository_id' => $repository->github_id,
            'repository_full_name' => $repository->full_name,
            'pull_request_number' => (int) $prData['number'],
            'pull_request_title' => (string) ($prData['title'] ?? ''),
            'pull_request_body' => isset($prData['body']) && is_string($prData['body']) ? $prData['body'] : null,
            'base_branch' => (string) ($prData['base']['ref'] ?? ''),
            'head_branch' => (string) ($prData['head']['ref'] ?? ''),
            'head_sha' => (string) ($prData['head']['sha'] ?? ''),
            'sender_login' => $senderLogin,
            'author' => [
                'login' => (string) ($prData['user']['login'] ?? ''),
                'avatar_url' => isset($prData['user']['avatar_url']) && is_string($prData['user']['avatar_url']) ? $prData['user']['avatar_url'] : null,
            ],
            'is_draft' => (bool) ($prData['draft'] ?? false),
            'assignees' => $this->extractUsers($prData['assignees'] ?? []),
            'reviewers' => $this->extractUsers($prData['requested_reviewers'] ?? []),
            'labels' => $this->extractLabels($prData['labels'] ?? []),
        ];
    }

    /**
     * Extract user information from GitHub API response.
     *
     * @param  array<int, array<string, mixed>>  $users
     * @return array<int, array{login: string, avatar_url: string|null}>
     */
    private function extractUsers(array $users): array
    {
        return array_map(fn (array $user): array => [
            'login' => (string) ($user['login'] ?? ''),
            'avatar_url' => isset($user['avatar_url']) && is_string($user['avatar_url']) ? $user['avatar_url'] : null,
        ], $users);
    }

    /**
     * Extract label information from GitHub API response.
     *
     * @param  array<int, array<string, mixed>>  $labels
     * @return array<int, array{name: string, color: string}>
     */
    private function extractLabels(array $labels): array
    {
        return array_map(fn (array $label): array => [
            'name' => (string) ($label['name'] ?? ''),
            'color' => (string) ($label['color'] ?? ''),
        ], $labels);
    }

    /**
     * Post an acknowledgment comment to the PR.
     */
    private function postAcknowledgmentComment(int $installationId, string $owner, string $repo, int $prNumber): ?int
    {
        try {
            $comment = $this->githubApi->createIssueComment(
                installationId: $installationId,
                owner: $owner,
                repo: $repo,
                number: $prNumber,
                body: $this->getAcknowledgmentMessage()
            );

            return (int) ($comment['id'] ?? 0) ?: null;
        } catch (Throwable $throwable) {
            Log::warning('Failed to post acknowledgment comment', [
                'owner' => $owner,
                'repo' => $repo,
                'pr_number' => $prNumber,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the acknowledgment message for manual review.
     */
    private function getAcknowledgmentMessage(): string
    {
        return "**Sentinel**: Starting code review...\n\nI'll analyze the changes in this pull request and post my findings shortly.";
    }
}
