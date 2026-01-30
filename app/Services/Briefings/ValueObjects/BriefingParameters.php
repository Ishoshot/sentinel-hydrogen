<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

final readonly class BriefingParameters
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(public array $values = []) {}

    /**
     * Create parameters from an array.
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * Get a parameter value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /**
     * Determine if the parameters are empty.
     */
    public function isEmpty(): bool
    {
        return $this->values === [];
    }
}
