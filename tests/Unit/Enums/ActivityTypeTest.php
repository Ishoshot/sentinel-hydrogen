<?php

declare(strict_types=1);

use App\Enums\Workspace\ActivityType;

it('returns all values', function (): void {
    $values = ActivityType::values();

    expect($values)->toBeArray()
        ->toContain('workspace.created')
        ->toContain('member.invited')
        ->toContain('github.connected')
        ->toContain('run.created')
        ->toContain('provider_key.updated')
        ->toContain('subscription.created');
});

it('returns correct labels for workspace activities', function (): void {
    expect(ActivityType::WorkspaceCreated->label())->toBe('Workspace Created');
    expect(ActivityType::WorkspaceUpdated->label())->toBe('Workspace Updated');
    expect(ActivityType::WorkspaceDeleted->label())->toBe('Workspace Deleted');
});

it('returns correct labels for member activities', function (): void {
    expect(ActivityType::MemberInvited->label())->toBe('Member Invited');
    expect(ActivityType::MemberJoined->label())->toBe('Member Joined');
    expect(ActivityType::MemberRemoved->label())->toBe('Member Removed');
    expect(ActivityType::MemberRoleUpdated->label())->toBe('Member Role Updated');
});

it('returns correct labels for github activities', function (): void {
    expect(ActivityType::GitHubConnected->label())->toBe('GitHub Connected');
    expect(ActivityType::GitHubDisconnected->label())->toBe('GitHub Disconnected');
    expect(ActivityType::RepositoriesSynced->label())->toBe('Repositories Synced');
    expect(ActivityType::RepositorySettingsUpdated->label())->toBe('Repository Settings Updated');
});

it('returns correct labels for review activities', function (): void {
    expect(ActivityType::RunCreated->label())->toBe('Review Run Created');
    expect(ActivityType::RunCompleted->label())->toBe('Review Run Completed');
    expect(ActivityType::RunFailed->label())->toBe('Review Run Failed');
    expect(ActivityType::RunSkipped->label())->toBe('Review Run Skipped');
    expect(ActivityType::AnnotationsPosted->label())->toBe('Annotations Posted');
});

it('returns correct labels for provider key activities', function (): void {
    expect(ActivityType::ProviderKeyUpdated->label())->toBe('Provider Key Configured');
    expect(ActivityType::ProviderKeyDeleted->label())->toBe('Provider Key Deleted');
});

it('returns correct labels for billing activities', function (): void {
    expect(ActivityType::SubscriptionCreated->label())->toBe('Subscription Created');
    expect(ActivityType::SubscriptionUpgraded->label())->toBe('Subscription Upgraded');
    expect(ActivityType::SubscriptionCanceled->label())->toBe('Subscription Canceled');
    expect(ActivityType::PlanLimitReached->label())->toBe('Plan Limit Reached');
});

it('returns correct icons for all activities', function (): void {
    expect(ActivityType::WorkspaceCreated->icon())->toBe('building');
    expect(ActivityType::WorkspaceUpdated->icon())->toBe('pencil');
    expect(ActivityType::WorkspaceDeleted->icon())->toBe('trash');
    expect(ActivityType::MemberInvited->icon())->toBe('user-plus');
    expect(ActivityType::MemberJoined->icon())->toBe('user-check');
    expect(ActivityType::MemberRemoved->icon())->toBe('user-minus');
    expect(ActivityType::MemberRoleUpdated->icon())->toBe('shield');
    expect(ActivityType::GitHubConnected->icon())->toBe('github');
    expect(ActivityType::GitHubDisconnected->icon())->toBe('github');
    expect(ActivityType::RepositoriesSynced->icon())->toBe('refresh');
    expect(ActivityType::RepositorySettingsUpdated->icon())->toBe('settings');
    expect(ActivityType::RunCreated->icon())->toBe('play');
    expect(ActivityType::RunCompleted->icon())->toBe('check-circle');
    expect(ActivityType::RunFailed->icon())->toBe('x-circle');
    expect(ActivityType::RunSkipped->icon())->toBe('skip-forward');
    expect(ActivityType::AnnotationsPosted->icon())->toBe('message-circle');
    expect(ActivityType::ProviderKeyUpdated->icon())->toBe('key');
    expect(ActivityType::ProviderKeyDeleted->icon())->toBe('key');
    expect(ActivityType::SubscriptionCreated->icon())->toBe('credit-card');
    expect(ActivityType::SubscriptionUpgraded->icon())->toBe('arrow-up-right');
    expect(ActivityType::SubscriptionCanceled->icon())->toBe('x-circle');
    expect(ActivityType::PlanLimitReached->icon())->toBe('alert-triangle');
});

it('returns correct categories', function (): void {
    expect(ActivityType::WorkspaceCreated->category())->toBe('workspace');
    expect(ActivityType::WorkspaceUpdated->category())->toBe('workspace');
    expect(ActivityType::WorkspaceDeleted->category())->toBe('workspace');
    expect(ActivityType::MemberInvited->category())->toBe('member');
    expect(ActivityType::MemberJoined->category())->toBe('member');
    expect(ActivityType::MemberRemoved->category())->toBe('member');
    expect(ActivityType::MemberRoleUpdated->category())->toBe('member');
    expect(ActivityType::GitHubConnected->category())->toBe('github');
    expect(ActivityType::GitHubDisconnected->category())->toBe('github');
    expect(ActivityType::RepositoriesSynced->category())->toBe('github');
    expect(ActivityType::RepositorySettingsUpdated->category())->toBe('github');
    expect(ActivityType::RunCreated->category())->toBe('review');
    expect(ActivityType::RunCompleted->category())->toBe('review');
    expect(ActivityType::RunFailed->category())->toBe('review');
    expect(ActivityType::RunSkipped->category())->toBe('review');
    expect(ActivityType::AnnotationsPosted->category())->toBe('review');
    expect(ActivityType::ProviderKeyUpdated->category())->toBe('settings');
    expect(ActivityType::ProviderKeyDeleted->category())->toBe('settings');
    expect(ActivityType::SubscriptionCreated->category())->toBe('billing');
    expect(ActivityType::SubscriptionUpgraded->category())->toBe('billing');
    expect(ActivityType::SubscriptionCanceled->category())->toBe('billing');
    expect(ActivityType::PlanLimitReached->category())->toBe('billing');
});
