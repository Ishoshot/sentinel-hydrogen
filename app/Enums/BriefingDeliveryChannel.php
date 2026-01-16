<?php

declare(strict_types=1);

namespace App\Enums;

enum BriefingDeliveryChannel: string
{
    case Push = 'push';
    case Email = 'email';
    case Slack = 'slack';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human-readable label for this delivery channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Push => 'Push Notification',
            self::Email => 'Email',
            self::Slack => 'Slack',
        };
    }
}
