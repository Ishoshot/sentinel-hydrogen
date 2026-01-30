<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

use App\Enums\Briefings\BriefingGenerationStatus;

final readonly class BriefingGenerationStatusSet
{
    /**
     * Create a set of briefing generation statuses.
     *
     * @param  array<int, BriefingGenerationStatus>  $statuses
     */
    public function __construct(
        public array $statuses = [],
    ) {}

    /**
     * Normalize status strings into enum values.
     *
     * @param  array<int, string>  $statuses
     */
    public static function fromStrings(array $statuses): self
    {
        $resolved = [];
        $seen = [];

        foreach ($statuses as $status) {
            $enum = BriefingGenerationStatus::tryFrom((string) $status);
            if ($enum !== null && ! isset($seen[$enum->value])) {
                $seen[$enum->value] = true;
                $resolved[] = $enum;
            }
        }

        return new self($resolved);
    }

    /**
     * Determine if the status set is empty.
     */
    public function isEmpty(): bool
    {
        return $this->statuses === [];
    }

    /**
     * Convert the statuses to string values.
     *
     * @return array<int, string>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (BriefingGenerationStatus $status): string => $status->value,
            $this->statuses,
        );
    }
}
