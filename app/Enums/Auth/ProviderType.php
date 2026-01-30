<?php

declare(strict_types=1);

namespace App\Enums\Auth;

enum ProviderType: string
{
    case GitHub = 'github';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human-readable label for this provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
        };
    }

    /**
     * Get the icon name for this provider.
     */
    public function icon(): string
    {
        return match ($this) {
            self::GitHub => 'github',
        };
    }
}
