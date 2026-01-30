<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

use App\Services\Contracts\EnforcementResult;

/**
 * Result object for briefing limit enforcement checks.
 */
final readonly class BriefingLimitResult implements EnforcementResult
{
    /**
     * Create a new briefing limit result.
     */
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
    ) {}

    /**
     * Create an allowed result.
     */
    public static function allow(): self
    {
        return new self(true);
    }

    /**
     * Create a denied result with a reason.
     */
    public static function deny(string $reason): self
    {
        return new self(false, $reason);
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
     * Get the denial reason.
     */
    public function getMessage(): ?string
    {
        return $this->reason;
    }
}
