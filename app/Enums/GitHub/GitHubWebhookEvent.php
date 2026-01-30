<?php

declare(strict_types=1);

namespace App\Enums\GitHub;

enum GitHubWebhookEvent: string
{
    // App lifecycle events (automatically received)
    case Installation = 'installation';
    case InstallationRepositories = 'installation_repositories';
    case InstallationTarget = 'installation_target';
    case Meta = 'meta';

    // CI/CD events
    case CheckRun = 'check_run';
    case CheckSuite = 'check_suite';

    // Repository events
    case Create = 'create';
    case Push = 'push';

    // Code review events
    case PullRequest = 'pull_request';
    case PullRequestReview = 'pull_request_review';
    case PullRequestReviewComment = 'pull_request_review_comment';
    case CommitComment = 'commit_comment';

    // Issues and discussions
    case Issues = 'issues';
    case IssueComment = 'issue_comment';
    case Discussion = 'discussion';
    case DiscussionComment = 'discussion_comment';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human-readable label for this event.
     */
    public function label(): string
    {
        return match ($this) {
            self::Installation => 'Installation',
            self::InstallationRepositories => 'Installation Repositories',
            self::InstallationTarget => 'Installation Target',
            self::Meta => 'Meta',
            self::CheckRun => 'Check Run',
            self::CheckSuite => 'Check Suite',
            self::Create => 'Create',
            self::Push => 'Push',
            self::PullRequest => 'Pull Request',
            self::PullRequestReview => 'Pull Request Review',
            self::PullRequestReviewComment => 'Pull Request Review Comment',
            self::CommitComment => 'Commit Comment',
            self::Issues => 'Issues',
            self::IssueComment => 'Issue Comment',
            self::Discussion => 'Discussion',
            self::DiscussionComment => 'Discussion Comment',
        };
    }

    /**
     * Check if this event should be processed immediately (not queued).
     */
    public function shouldProcessImmediately(): bool
    {
        return in_array($this, [
            self::Installation,
            self::InstallationRepositories,
            self::InstallationTarget,
            self::Meta,
        ], true);
    }

    /**
     * Check if this event triggers code review.
     */
    public function triggersReview(): bool
    {
        return $this === self::PullRequest;
    }
}
