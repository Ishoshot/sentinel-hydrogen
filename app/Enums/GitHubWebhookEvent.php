<?php

declare(strict_types=1);

namespace App\Enums;

enum GitHubWebhookEvent: string
{
    case Installation = 'installation';
    case InstallationRepositories = 'installation_repositories';
    case PullRequest = 'pull_request';
    case PullRequestReview = 'pull_request_review';
    case Push = 'push';

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
            self::PullRequest => 'Pull Request',
            self::PullRequestReview => 'Pull Request Review',
            self::Push => 'Push',
        };
    }

    /**
     * Check if this event should be processed immediately (not queued).
     */
    public function shouldProcessImmediately(): bool
    {
        return in_array($this, [self::Installation, self::InstallationRepositories], true);
    }

    /**
     * Check if this event triggers code review.
     */
    public function triggersReview(): bool
    {
        return $this === self::PullRequest;
    }
}
