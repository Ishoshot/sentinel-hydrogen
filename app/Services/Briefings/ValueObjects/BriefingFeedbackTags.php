<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

final readonly class BriefingFeedbackTags
{
    /**
     * Create a set of feedback tags.
     *
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public array $tags = [],
    ) {}

    /**
     * Normalize feedback tags from an array.
     *
     * @param  array<int, string>  $tags
     */
    public static function fromArray(array $tags): self
    {
        $filtered = array_filter($tags, is_string(...));

        return new self(array_values($filtered));
    }

    /**
     * Determine if no tags were supplied.
     */
    public function isEmpty(): bool
    {
        return $this->tags === [];
    }

    /**
     * Convert feedback tags to an array.
     *
     * @return array<int, string>
     */
    public function toArray(): array
    {
        return $this->tags;
    }
}
