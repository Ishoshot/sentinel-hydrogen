<?php

declare(strict_types=1);

namespace App\Enums;

enum InstallationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Uninstalled = 'uninstalled';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Uninstalled => 'Uninstalled',
        };
    }

    /**
     * Check if the installation is usable for API calls.
     */
    public function isUsable(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if the installation can be reactivated.
     */
    public function canReactivate(): bool
    {
        return $this === self::Suspended;
    }
}
