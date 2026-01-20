<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Defines all valid queue names and their base priorities.
 *
 * Lower priority values are processed first (higher priority).
 * Queues are organized by:
 * - System operations (critical internal tasks)
 * - Webhooks (external event intake)
 * - Reviews (AI code review execution)
 * - General workloads (notifications, sync, etc.)
 * - Long-running tasks (bulk operations)
 */
enum Queue: string
{
    // System & Critical (Priority 1-10)
    case System = 'system';
    case Webhooks = 'webhooks';

    // Reviews - Tier-based (Priority 20-40)
    case ReviewsEnterprise = 'reviews-enterprise';
    case ReviewsPaid = 'reviews-paid';
    case ReviewsDefault = 'reviews-default';

    // Briefings (Priority 45-50)
    case BriefingsDefault = 'briefings-default';

    // Commands (Priority 48 - between briefings and annotations)
    case Commands = 'commands';

    // Post-processing (Priority 50-60)
    case Annotations = 'annotations';
    case Notifications = 'notifications';

    // General (Priority 70-80)
    case Sync = 'sync';
    case Default = 'default';

    // Code Indexing (Priority 85)
    case CodeIndexing = 'code-indexing';

    // Long-running & Bulk (Priority 90-100)
    case LongRunning = 'long-running';
    case Bulk = 'bulk';

    /**
     * Get all queues sorted by priority (highest first).
     *
     * @return array<int, self>
     */
    public static function byPriority(): array
    {
        $queues = self::cases();
        usort($queues, fn (self $a, self $b): int => $a->priority() <=> $b->priority());

        return $queues;
    }

    /**
     * Get all queue names as an array for Horizon configuration.
     *
     * @return array<int, string>
     */
    public static function allNames(): array
    {
        return array_map(fn (self $queue): string => $queue->value, self::byPriority());
    }

    /**
     * Get the review queue for a given tier.
     */
    public static function reviewQueueForTier(string $tier): self
    {
        return match ($tier) {
            'enterprise' => self::ReviewsEnterprise,
            'paid', 'pro', 'team' => self::ReviewsPaid,
            default => self::ReviewsDefault,
        };
    }

    /**
     * Get the base priority for this queue.
     *
     * Lower values = higher priority (processed first).
     */
    public function priority(): int
    {
        return match ($this) {
            self::System => 1,
            self::Webhooks => 5,
            self::ReviewsEnterprise => 20,
            self::ReviewsPaid => 30,
            self::ReviewsDefault => 40,
            self::BriefingsDefault => 45,
            self::Commands => 48,
            self::Annotations => 50,
            self::Notifications => 55,
            self::Sync => 70,
            self::Default => 80,
            self::CodeIndexing => 85,
            self::LongRunning => 90,
            self::Bulk => 100,
        };
    }

    /**
     * Get a human-readable label for this queue.
     */
    public function label(): string
    {
        return match ($this) {
            self::System => 'System',
            self::Webhooks => 'Webhooks',
            self::ReviewsEnterprise => 'Reviews (Enterprise)',
            self::ReviewsPaid => 'Reviews (Paid)',
            self::ReviewsDefault => 'Reviews (Default)',
            self::BriefingsDefault => 'Briefings',
            self::Commands => 'Commands',
            self::Annotations => 'Annotations',
            self::Notifications => 'Notifications',
            self::Sync => 'Sync',
            self::Default => 'Default',
            self::CodeIndexing => 'Code Indexing',
            self::LongRunning => 'Long Running',
            self::Bulk => 'Bulk Operations',
        };
    }

    /**
     * Get the timeout in seconds for jobs on this queue.
     */
    public function timeout(): int
    {
        return match ($this) {
            self::System => 60,
            self::Webhooks => 30,
            self::ReviewsEnterprise => 300,
            self::ReviewsPaid => 300,
            self::ReviewsDefault => 300,
            self::BriefingsDefault => 300,
            self::Commands => 300,
            self::Annotations => 60,
            self::Notifications => 30,
            self::Sync => 120,
            self::Default => 60,
            self::CodeIndexing => 300,
            self::LongRunning => 600,
            self::Bulk => 900,
        };
    }

    /**
     * Get the maximum retry attempts for jobs on this queue.
     */
    public function tries(): int
    {
        return match ($this) {
            self::System => 3,
            self::Webhooks => 5,
            self::ReviewsEnterprise => 3,
            self::ReviewsPaid => 3,
            self::ReviewsDefault => 3,
            self::BriefingsDefault => 3,
            self::Commands => 2,
            self::Annotations => 3,
            self::Notifications => 3,
            self::Sync => 3,
            self::Default => 3,
            self::CodeIndexing => 2,
            self::LongRunning => 2,
            self::Bulk => 2,
        };
    }

    /**
     * Check if this queue is for review jobs.
     */
    public function isReviewQueue(): bool
    {
        return in_array($this, [
            self::ReviewsEnterprise,
            self::ReviewsPaid,
            self::ReviewsDefault,
        ], true);
    }

    /**
     * Check if this queue is high priority (system-critical).
     */
    public function isHighPriority(): bool
    {
        return $this->priority() <= 10;
    }

    /**
     * Check if this queue is for long-running operations.
     */
    public function isLongRunning(): bool
    {
        return in_array($this, [
            self::LongRunning,
            self::Bulk,
        ], true);
    }
}
