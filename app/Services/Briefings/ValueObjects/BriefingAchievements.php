<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

final readonly class BriefingAchievements
{
    /**
     * @param  array<int, Achievement>  $items
     */
    public function __construct(public array $items = []) {}

    /**
     * Create achievements from an array payload.
     *
     * @param  array<int, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $items = [];

        foreach ($payload as $item) {
            if (! is_array($item)) {
                continue;
            }

            /** @var array<string, mixed> $item */
            $items[] = Achievement::fromArray($item);
        }

        return new self($items);
    }

    /**
     * Create achievements from a set of Achievement objects.
     *
     * @param  array<int, Achievement>  $items
     */
    public static function fromItems(array $items): self
    {
        return new self($items);
    }

    /**
     * @return array<int, array{type: string, title: string, description: string, value: int|float|string|null}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (Achievement $achievement): array => $achievement->toArray(),
            $this->items
        );
    }

    /**
     * Determine if there are any achievements.
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
