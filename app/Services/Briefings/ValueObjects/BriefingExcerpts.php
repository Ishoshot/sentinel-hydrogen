<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

final readonly class BriefingExcerpts
{
    /**
     * @param  array<string, string>  $values
     */
    public function __construct(public array $values = []) {}

    /**
     * Create excerpts from an array payload.
     *
     * @param  array<string, string>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * Get an excerpt by key.
     */
    public function get(string $key, string $default = ''): string
    {
        return $this->values[$key] ?? $default;
    }
}
