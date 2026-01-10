<?php

declare(strict_types=1);

namespace App\Enums;

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
        };
    }
}
