<?php

declare(strict_types=1);

namespace App\Actions\Briefings\Support;

use App\Models\BriefingSubscription;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;
use Illuminate\Support\Facades\Log;

/**
 * Handles denied subscription results by logging and conditionally deferring.
 */
final readonly class ScheduledBriefingDenialHandler
{
    /**
     * Handle a subscription denied by limits.
     */
    public function handle(BriefingSubscription $subscription, BriefingLimitResult $result): void
    {
        Log::warning('Scheduled briefing blocked by limits', [
            'subscription_id' => $subscription->id,
            'reason' => $result->reason,
        ]);

        if ($this->shouldDefer($result)) {
            $subscription->markDeferred();
        }
    }

    /**
     * Determine if denied subscriptions should be deferred.
     */
    private function shouldDefer(BriefingLimitResult $result): bool
    {
        $reason = $result->reason ?? '';

        return $reason === '' || ! str_contains($reason, 'currently generating');
    }
}
