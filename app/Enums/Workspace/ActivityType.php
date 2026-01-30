<?php

declare(strict_types=1);

namespace App\Enums\Workspace;

enum ActivityType: string
{
    // Workspace
    case WorkspaceCreated = 'workspace.created';
    case WorkspaceUpdated = 'workspace.updated';
    case WorkspaceDeleted = 'workspace.deleted';

    // Members
    case MemberInvited = 'member.invited';
    case MemberJoined = 'member.joined';
    case MemberRemoved = 'member.removed';
    case MemberRoleUpdated = 'member.role_updated';

    // GitHub Integration
    case GitHubConnected = 'github.connected';
    case GitHubDisconnected = 'github.disconnected';
    case RepositoriesSynced = 'repositories.synced';
    case RepositorySettingsUpdated = 'repository.settings_updated';

    // Reviews
    case RunCreated = 'run.created';
    case RunCompleted = 'run.completed';
    case RunFailed = 'run.failed';
    case RunSkipped = 'run.skipped';
    case AnnotationsPosted = 'annotations.posted';

    // Provider Keys (BYOK)
    case ProviderKeyUpdated = 'provider_key.updated';
    case ProviderKeyDeleted = 'provider_key.deleted';

    // Billing
    case SubscriptionCreated = 'subscription.created';
    case SubscriptionUpgraded = 'subscription.upgraded';
    case SubscriptionDowngraded = 'subscription.downgraded';
    case SubscriptionCanceled = 'subscription.canceled';
    case PlanLimitReached = 'plan.limit_reached';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human-readable label for this activity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::WorkspaceCreated => 'Workspace Created',
            self::WorkspaceUpdated => 'Workspace Updated',
            self::WorkspaceDeleted => 'Workspace Deleted',
            self::MemberInvited => 'Member Invited',
            self::MemberJoined => 'Member Joined',
            self::MemberRemoved => 'Member Removed',
            self::MemberRoleUpdated => 'Member Role Updated',
            self::GitHubConnected => 'GitHub Connected',
            self::GitHubDisconnected => 'GitHub Disconnected',
            self::RepositoriesSynced => 'Repositories Synced',
            self::RepositorySettingsUpdated => 'Repository Settings Updated',
            self::RunCreated => 'Review Run Created',
            self::RunCompleted => 'Review Run Completed',
            self::RunFailed => 'Review Run Failed',
            self::RunSkipped => 'Review Run Skipped',
            self::AnnotationsPosted => 'Annotations Posted',
            self::ProviderKeyUpdated => 'Provider Key Configured',
            self::ProviderKeyDeleted => 'Provider Key Deleted',
            self::SubscriptionCreated => 'Subscription Created',
            self::SubscriptionUpgraded => 'Subscription Upgraded',
            self::SubscriptionDowngraded => 'Subscription Downgraded',
            self::SubscriptionCanceled => 'Subscription Canceled',
            self::PlanLimitReached => 'Plan Limit Reached',
        };
    }

    /**
     * Get the icon name for this activity type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::WorkspaceCreated => 'building',
            self::WorkspaceUpdated => 'pencil',
            self::WorkspaceDeleted => 'trash',
            self::MemberInvited => 'user-plus',
            self::MemberJoined => 'user-check',
            self::MemberRemoved => 'user-minus',
            self::MemberRoleUpdated => 'shield',
            self::GitHubConnected => 'github',
            self::GitHubDisconnected => 'github',
            self::RepositoriesSynced => 'refresh',
            self::RepositorySettingsUpdated => 'settings',
            self::RunCreated => 'play',
            self::RunCompleted => 'check-circle',
            self::RunFailed => 'x-circle',
            self::RunSkipped => 'skip-forward',
            self::AnnotationsPosted => 'message-circle',
            self::ProviderKeyUpdated => 'key',
            self::ProviderKeyDeleted => 'key',
            self::SubscriptionCreated => 'credit-card',
            self::SubscriptionUpgraded => 'arrow-up-right',
            self::SubscriptionDowngraded => 'arrow-down-right',
            self::SubscriptionCanceled => 'x-circle',
            self::PlanLimitReached => 'alert-triangle',
        };
    }

    /**
     * Get the category for this activity type.
     */
    public function category(): string
    {
        return match ($this) {
            self::WorkspaceCreated,
            self::WorkspaceUpdated,
            self::WorkspaceDeleted => 'workspace',
            self::MemberInvited,
            self::MemberJoined,
            self::MemberRemoved,
            self::MemberRoleUpdated => 'member',
            self::GitHubConnected,
            self::GitHubDisconnected,
            self::RepositoriesSynced,
            self::RepositorySettingsUpdated => 'github',
            self::RunCreated,
            self::RunCompleted,
            self::RunFailed,
            self::RunSkipped,
            self::AnnotationsPosted => 'review',
            self::ProviderKeyUpdated,
            self::ProviderKeyDeleted => 'settings',
            self::SubscriptionCreated,
            self::SubscriptionUpgraded,
            self::SubscriptionDowngraded,
            self::SubscriptionCanceled,
            self::PlanLimitReached => 'billing',
        };
    }
}
