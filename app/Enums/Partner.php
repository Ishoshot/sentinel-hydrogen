<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * External partners that send webhooks to our system.
 */
enum Partner: string
{
    case Polar = 'polar';
    case GitHub = 'github';

    /**
     * Get the human-readable label for this partner.
     */
    public function label(): string
    {
        return match ($this) {
            self::Polar => 'Polar',
            self::GitHub => 'GitHub',
        };
    }
}
