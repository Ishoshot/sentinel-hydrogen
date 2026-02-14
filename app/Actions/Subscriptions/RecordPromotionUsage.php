<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Enums\Promotions\PromotionUsageStatus;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Subscription;
use App\Models\Workspace;

final class RecordPromotionUsage
{
    /**
     * Record pending promotion usage created during checkout initiation.
     */
    public function pendingCheckout(Workspace $workspace, Promotion $promotion, string $checkoutUrl): void
    {
        PromotionUsage::query()->create([
            'promotion_id' => $promotion->id,
            'workspace_id' => $workspace->id,
            'status' => PromotionUsageStatus::Pending,
            'checkout_url' => $checkoutUrl,
        ]);
    }

    /**
     * Record completed promotion usage after subscription activation.
     */
    public function completed(Workspace $workspace, Promotion $promotion, Subscription $subscription): void
    {
        PromotionUsage::query()->create([
            'promotion_id' => $promotion->id,
            'workspace_id' => $workspace->id,
            'subscription_id' => $subscription->id,
            'status' => PromotionUsageStatus::Completed,
            'confirmed_at' => now(),
        ]);

        $promotion->incrementUsage();
    }
}
