<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Enums\Queue\Queue;

/**
 * Handles score initialization and score-based queue selection with tie-breaking.
 */
final readonly class QueueScorer
{
    private const int MAX_PRIORITY = 100;

    /**
     * Initialize all queue scores based on their base priority.
     *
     * Higher base priority (lower number) = higher initial score.
     * We invert the priority so that queues with lower priority numbers
     * start with higher scores.
     *
     * @return non-empty-array<string, int>
     */
    public function initializeScores(): array
    {
        $scores = [];

        foreach (Queue::cases() as $queue) {
            $scores[$queue->value] = self::MAX_PRIORITY - $queue->priority();
        }

        return $scores;
    }

    /**
     * Select the queue with the highest score.
     *
     * When multiple queues tie for the highest score, the one with the
     * lowest base priority number (highest inherent priority) wins.
     *
     * @param  non-empty-array<string, int>  $scores
     */
    public function selectByScore(array $scores): Queue
    {
        $maxScore = max($scores);
        $candidates = array_keys(array_filter($scores, fn (int $score): bool => $score === $maxScore));

        $selectedValue = $candidates[0];
        $selectedPriority = Queue::from($selectedValue)->priority();

        foreach ($candidates as $candidate) {
            $candidatePriority = Queue::from($candidate)->priority();
            if ($candidatePriority < $selectedPriority) {
                $selectedValue = $candidate;
                $selectedPriority = $candidatePriority;
            }
        }

        return Queue::from($selectedValue);
    }
}
