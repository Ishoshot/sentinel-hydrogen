<?php

declare(strict_types=1);

namespace App\Actions\Briefings\Support;

use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use App\Notifications\Briefings\BriefingDeliveryNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles briefing delivery via email notification.
 */
final readonly class EmailBriefingDeliverer
{
    /**
     * Deliver a generated briefing to the subscriber via email.
     */
    public function deliver(BriefingGeneration $generation, BriefingSubscription $subscription): void
    {
        $subscription->loadMissing('user');
        $user = $subscription->user;

        if ($user === null || $user->email === null) {
            Log::warning('Cannot deliver briefing via email - no user or email', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        try {
            $user->notify(new BriefingDeliveryNotification($generation));

            Log::info('Briefing delivered via email', [
                'generation_id' => $generation->id,
                'user_email' => $user->email,
            ]);
        } catch (Throwable $throwable) {
            Log::error('Failed to deliver briefing via email', [
                'generation_id' => $generation->id,
                'user_email' => $user->email,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }
}
