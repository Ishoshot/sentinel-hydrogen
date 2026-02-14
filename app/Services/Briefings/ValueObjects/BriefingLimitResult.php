<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

use App\Enums\Briefings\BriefingLimitReasonCode;
use App\Services\Contracts\EnforcementResult;

/**
 * Result object for briefing limit enforcement checks.
 *
 * Contains the enforcement decision along with an optional human-readable
 * reason and actionable guidance for the user to resolve the denial.
 */
final readonly class BriefingLimitResult implements EnforcementResult
{
    /**
     * Create a new briefing limit result.
     *
     * @param  bool  $allowed  Whether the operation is allowed
     * @param  string|null  $reason  Human-readable denial reason
     * @param  BriefingLimitReasonCode|null  $reasonCode  Machine-readable reason code
     * @param  string|null  $guidance  Actionable guidance for the user to resolve the denial
     */
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?BriefingLimitReasonCode $reasonCode = null,
        public ?string $guidance = null,
    ) {}

    /**
     * Create an allowed result.
     */
    public static function allow(): self
    {
        return new self(true);
    }

    /**
     * Create a denied result with a reason and optional guidance.
     *
     * @param  string  $reason  Human-readable denial reason
     * @param  BriefingLimitReasonCode|null  $reasonCode  Machine-readable reason code
     * @param  string|null  $guidance  Actionable guidance for the user to resolve the denial
     */
    public static function deny(
        string $reason,
        ?BriefingLimitReasonCode $reasonCode = null,
        ?string $guidance = null,
    ): self {
        return new self(false, $reason, $reasonCode, $guidance);
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
