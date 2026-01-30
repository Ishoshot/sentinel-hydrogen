<?php

declare(strict_types=1);

namespace App\Services\Plans\ValueObjects;

use App\Services\Contracts\EnforcementResult;

/**
 * Result object for plan limit enforcement checks.
 */
final readonly class PlanLimitResult implements EnforcementResult
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

    /**
     * Check if the result is allowed.
     */
    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Check if the result is denied.
     */
    public function isDenied(): bool
    {
        return ! $this->allowed;
    }

    /**
     * Get the denial message.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}
