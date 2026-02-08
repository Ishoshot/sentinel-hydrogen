<?php

declare(strict_types=1);

namespace App\Actions\Admin\Briefings;

use App\Models\Briefing;
use InvalidArgumentException;

/**
 * Delete a briefing template.
 */
final readonly class DeleteBriefing
{
    /**
     * Delete a briefing.
     *
     * @throws InvalidArgumentException If the briefing is in use.
     */
    public function handle(Briefing $briefing): void
    {
        $generationCount = $briefing->generations()->count();

        if ($generationCount > 0) {
            throw new InvalidArgumentException(
                sprintf('Cannot delete this briefing as it has %d generation(s).', $generationCount)
            );
        }

        $activeSubscriptionCount = $briefing->subscriptions()->where('is_active', true)->count();

        if ($activeSubscriptionCount > 0) {
            throw new InvalidArgumentException(
                sprintf('Cannot delete this briefing as it has %d active subscription(s).', $activeSubscriptionCount)
            );
        }

        $briefing->delete();
    }
}
