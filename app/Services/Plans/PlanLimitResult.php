<?php

declare(strict_types=1);

namespace App\Services\Plans;

/**
 * Result object for plan limit enforcement checks.
 */
final readonly class PlanLimitResult
{
    /**
     * Create a new plan limit result.
     */
    public function __construct(
        public bool $allowed,
        public ?string $message = null,
        public ?string $code = null,
    ) {}

    /**
     * Create an allowed result.
     */
    public static function allow(): self
    {
        return new self(true);
    }

    /**
     * Create a denied result with a message and code.
     */
    public static function deny(string $message, string $code): self
    {
        return new self(false, $message, $code);
    }
}
