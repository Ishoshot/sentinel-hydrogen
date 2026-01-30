<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Models\BriefingGeneration;
use App\Models\User;
use App\Services\Briefings\ValueObjects\BriefingFeedbackTags;

final readonly class RecordBriefingFeedback
{
    /**
     * Record feedback for a briefing generation.
     *
     * @param  BriefingGeneration  $generation  The generation receiving feedback
     * @param  User  $user  The user submitting feedback
     * @param  int  $rating  The feedback rating
     * @param  string|null  $comment  Optional feedback comment
     * @param  BriefingFeedbackTags  $tags  Tags describing the feedback
     * @return BriefingGeneration The updated generation
     */
    public function handle(
        BriefingGeneration $generation,
        User $user,
        int $rating,
        ?string $comment,
        BriefingFeedbackTags $tags,
    ): BriefingGeneration {
        $metadata = $generation->metadata ?? [];

        $feedbackEntries = $metadata['feedback'] ?? [];

        if (! is_array($feedbackEntries)) {
            $feedbackEntries = [];
        }

        $feedbackEntries[] = [
            'submitted_at' => now()->toIso8601String(),
            'user_id' => $user->id,
            'rating' => $rating,
            'comment' => $comment,
            'tags' => $tags->toArray(),
        ];

        $metadata['feedback'] = $feedbackEntries;

        $generation->update([
            'metadata' => $metadata,
        ]);

        return $generation;
    }
}
