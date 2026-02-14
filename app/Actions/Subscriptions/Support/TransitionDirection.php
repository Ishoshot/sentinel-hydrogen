<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions\Support;

use App\Enums\Billing\PlanTier;
use InvalidArgumentException;

/**
 * Represents the resolved direction of a subscription change.
 */
enum TransitionDirection: string
{
    case Subscribe = 'subscribe';
    case Upgrade = 'upgrade';
    case Downgrade = 'downgrade';
    case Cancel = 'cancel';

    /**
     * Resolve the transition direction between two plan tiers.
     *
     * @throws InvalidArgumentException if the tiers are identical
     */
    public static function resolve(PlanTier $current, PlanTier $target): self
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
     */
    public function acceptsPromotion(): bool
    {
        return in_array($this, [self::Subscribe, self::Upgrade], true);
    }
}
