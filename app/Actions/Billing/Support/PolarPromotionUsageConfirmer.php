<?php

declare(strict_types=1);

namespace App\Actions\Billing\Support;

use App\Enums\Promotions\PromotionUsageStatus;
use App\Models\PromotionUsage;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

final class PolarPromotionUsageConfirmer
{
    /**
     * Confirm pending promotion usage once a subscription is created.
     */
    public function confirm(Workspace $workspace, Subscription $subscription, mixed $promotionId): void
    {
        if (! is_string($promotionId) || $promotionId === '') {
            return;
        }

        if (! ctype_digit($promotionId)) {
            Log::warning('Invalid promotion_id format in webhook metadata', [
                'promotion_id' => $promotionId,
                'workspace_id' => $workspace->id,
            ]);

            return;
        }

        $usage = PromotionUsage::query()
            ->where('workspace_id', $workspace->id)
            ->where('promotion_id', (int) $promotionId)
            ->where('status', PromotionUsageStatus::Pending)
            ->first();

        if ($usage instanceof PromotionUsage) {
            $usage->confirm($subscription);

            Log::info('Promotion usage confirmed', [
                'promotion_id' => $promotionId,
                'workspace_id' => $workspace->id,
                'subscription_id' => $subscription->id,
            ]);
        }
    }
}
