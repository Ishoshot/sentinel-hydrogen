<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

final readonly class Achievement
{
    /**
     * Create a new achievement instance.
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $description,
        public int|float|string|null $value = null,
    ) {}

    /**
     * Create an achievement from an array payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $value = $payload['value'] ?? null;

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value) || is_object($value)) {
            $value = null;
        }

        return new self(
            type: (string) ($payload['type'] ?? 'milestone'),
            title: (string) ($payload['title'] ?? ''),
            description: (string) ($payload['description'] ?? ''),
            value: is_int($value) || is_float($value) || is_string($value) ? $value : null,
        );
    }

    /**
     * Convert the achievement to an array payload.
     *
     * @return array{type: string, title: string, description: string, value: int|float|string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'value' => $this->value,
        ];
    }
}
