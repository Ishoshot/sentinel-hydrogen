<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Billing interval options for subscriptions.
 */
enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * Get all billing interval values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a human-readable label for the interval.
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }
}
