<?php

declare(strict_types=1);

use App\Enums\GitHubWebhookEvent;

it('returns all values', function (): void {
    $values = GitHubWebhookEvent::values();

    expect($values)->toBeArray()
        ->toContain('installation')
        ->toContain('push')
        ->toContain('pull_request');
});

it('returns correct labels for all events', function (): void {
    expect(GitHubWebhookEvent::Installation->label())->toBe('Installation');
    expect(GitHubWebhookEvent::InstallationRepositories->label())->toBe('Installation Repositories');
    expect(GitHubWebhookEvent::InstallationTarget->label())->toBe('Installation Target');
    expect(GitHubWebhookEvent::Meta->label())->toBe('Meta');
    expect(GitHubWebhookEvent::CheckRun->label())->toBe('Check Run');
    expect(GitHubWebhookEvent::CheckSuite->label())->toBe('Check Suite');
    expect(GitHubWebhookEvent::Create->label())->toBe('Create');
    expect(GitHubWebhookEvent::Push->label())->toBe('Push');
    expect(GitHubWebhookEvent::PullRequest->label())->toBe('Pull Request');
    expect(GitHubWebhookEvent::PullRequestReview->label())->toBe('Pull Request Review');
    expect(GitHubWebhookEvent::PullRequestReviewComment->label())->toBe('Pull Request Review Comment');
    expect(GitHubWebhookEvent::CommitComment->label())->toBe('Commit Comment');
    expect(GitHubWebhookEvent::Issues->label())->toBe('Issues');
    expect(GitHubWebhookEvent::IssueComment->label())->toBe('Issue Comment');
    expect(GitHubWebhookEvent::Discussion->label())->toBe('Discussion');
    expect(GitHubWebhookEvent::DiscussionComment->label())->toBe('Discussion Comment');
});

it('identifies events that should be processed immediately', function (): void {
    expect(GitHubWebhookEvent::Installation->shouldProcessImmediately())->toBeTrue();
    expect(GitHubWebhookEvent::InstallationRepositories->shouldProcessImmediately())->toBeTrue();
    expect(GitHubWebhookEvent::InstallationTarget->shouldProcessImmediately())->toBeTrue();
    expect(GitHubWebhookEvent::Meta->shouldProcessImmediately())->toBeTrue();

    expect(GitHubWebhookEvent::PullRequest->shouldProcessImmediately())->toBeFalse();
    expect(GitHubWebhookEvent::Push->shouldProcessImmediately())->toBeFalse();
});

it('identifies events that trigger review', function (): void {
    expect(GitHubWebhookEvent::PullRequest->triggersReview())->toBeTrue();
    expect(GitHubWebhookEvent::Push->triggersReview())->toBeFalse();
    expect(GitHubWebhookEvent::Installation->triggersReview())->toBeFalse();
});
