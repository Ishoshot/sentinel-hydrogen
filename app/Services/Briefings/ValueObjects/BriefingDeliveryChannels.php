<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

use App\Enums\Briefings\BriefingDeliveryChannel;

final readonly class BriefingDeliveryChannels
{
    /**
     * @param  array<int, BriefingDeliveryChannel>  $channels
     */
    public function __construct(public array $channels) {}

    /**
     * Create delivery channels from raw string values.
     *
     * @param  array<int, string>  $channels
     */
    public static function fromStrings(array $channels): self
    {
        return new self(array_map(BriefingDeliveryChannel::from(...), $channels));
    }

    /**
     * Create delivery channels from enums.
     *
     * @param  array<int, BriefingDeliveryChannel>  $channels
     */
    public static function fromEnums(array $channels): self
    {
        return new self($channels);
    }

    /**
     * @return array<int, string>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (BriefingDeliveryChannel $channel): string => $channel->value,
            $this->channels
        );
    }

    /**
     * Determine if any channels were provided.
     */
    public function isEmpty(): bool
    {
        return $this->channels === [];
    }
}
