<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions\Support;

use App\Enums\Billing\PlanTier;
use InvalidArgumentException;

/**
 * Represents the resolved direction of a subscription change.
 */
final readonly class TransitionDirection
{
    public const string Subscribe = 'subscribe';

    public const string Upgrade = 'upgrade';

    public const string Downgrade = 'downgrade';

    public const string Cancel = 'cancel';

    /**
     * Prevent instantiation of utility class.
     */
    private function __construct() {}

    /**
     * Resolve the transition direction between two plan tiers.
     *
     * @return self::Subscribe|self::Upgrade|self::Downgrade|self::Cancel
     *
     * @throws InvalidArgumentException if the tiers are identical
     */
    public static function resolve(PlanTier $current, PlanTier $target): string
    {
        if ($current === $target) {
            throw new InvalidArgumentException('Workspace is already on the requested plan.');
        }

        if ($current->isFree()) {
            return self::Subscribe;
        }

        if ($target->isFree()) {
            return self::Cancel;
        }

        return $target->rank() > $current->rank() ? self::Upgrade : self::Downgrade;
    }

    /**
     * Whether this direction supports promotional codes.
     *
     * @param  self::Subscribe|self::Upgrade|self::Downgrade|self::Cancel  $direction
     */
    public static function acceptsPromotion(string $direction): bool
    {
        return in_array($direction, [self::Subscribe, self::Upgrade], true);
    }
}
