<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions\Support;

use App\Actions\Subscriptions\RecordPromotionUsage;
use App\Models\Promotion;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Promotions\Contracts\PromotionValidatorContract;
use InvalidArgumentException;

/**
 * Encapsulates promotion validation, usage recording, and response formatting.
 */
final readonly class PromotionHandler
{
    public function __construct(
        private PromotionValidatorContract $promotionValidator,
        private RecordPromotionUsage $recordPromotionUsage,
    ) {}

    /**
     * Validate a promotion code if the transition direction supports it.
     *
     * @throws InvalidArgumentException if the promotion code is invalid
     */
    public function validateIfApplicable(TransitionDirection $direction, ?string $promoCode): ?Promotion
    {
        if (! $direction->acceptsPromotion() || ! is_string($promoCode) || $promoCode === '') {
            return null;
        }

        $result = $this->promotionValidator->validate($promoCode);

        if ($result->failed()) {
            throw new InvalidArgumentException($result->message ?? 'Invalid promotion code.');
        }

        return $result->promotion;
    }

    /**
     * Record promotion usage as pending checkout.
     */
    public function recordPendingCheckout(Workspace $workspace, ?Promotion $promotion, string $checkoutUrl): void
    {
        if ($promotion instanceof Promotion) {
            $this->recordPromotionUsage->pendingCheckout($workspace, $promotion, $checkoutUrl);
        }
    }

    /**
     * Record promotion usage as completed.
     */
    public function recordCompleted(Workspace $workspace, ?Promotion $promotion, Subscription $subscription): void
    {
        if ($promotion instanceof Promotion) {
            $this->recordPromotionUsage->completed($workspace, $promotion, $subscription);
        }
    }
}
