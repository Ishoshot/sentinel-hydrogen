<?php

declare(strict_types=1);

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string>
     */
    public static function assignableRoles(): array
    {
        return [self::Admin->value, self::Member->value];
    }

    /**
     * Get the human-readable label for this role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /**
     * Check if this role can manage team members.
     */
    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Check if this role can manage workspace settings.
     */
    public function canManageSettings(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Check if this role can delete the workspace.
     */
    public function canDeleteWorkspace(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Check if this role can transfer workspace ownership.
     */
    public function canTransferOwnership(): bool
    {
        return $this === self::Owner;
    }
}
