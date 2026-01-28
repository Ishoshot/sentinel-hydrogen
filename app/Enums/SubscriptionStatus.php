<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Revoked = 'revoked';

    /**
     * Get all subscription status values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if the subscription status represents an active subscription.
     *
     * Active and Trialing statuses are considered active.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Active, self::Trialing], true);
    }
}
