<?php

declare(strict_types=1);

namespace App\Enums\Workspace;

enum ConnectionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disconnected = 'disconnected';
    case Failed = 'failed';

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
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Disconnected => 'Disconnected',
            self::Failed => 'Failed',
        };
    }

    /**
     * Check if the connection is usable for API calls.
     */
    public function isUsable(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if the connection can be reconnected.
     */
    public function canReconnect(): bool
    {
        return in_array($this, [self::Disconnected, self::Failed], true);
    }
}
