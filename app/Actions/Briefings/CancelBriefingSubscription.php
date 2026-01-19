<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Models\BriefingSubscription;

final readonly class CancelBriefingSubscription
{
    /**
     * Cancel a briefing subscription.
     *
     * @param  BriefingSubscription  $subscription  The subscription to cancel
     */
    public function handle(BriefingSubscription $subscription): void
    {
        $subscription->update([
            'is_active' => false,
        ]);
    }
}
