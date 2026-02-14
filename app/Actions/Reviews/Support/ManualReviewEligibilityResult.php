<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

use App\Models\Installation;

final readonly class ManualReviewEligibilityResult
{
    /**
     * Create a new eligibility result.
     */
    public function __construct(
        public bool $allowed,
        public ?Installation $installation = null,
        public ?string $message = null,
    ) {}

    /**
     * Create a passing eligibility result.
     */
    public static function allow(Installation $installation): self
    {
        return new self(allowed: true, installation: $installation);
    }

    /**
     * Create a failing eligibility result.
     */
    public static function deny(string $message): self
    {
        return new self(allowed: false, message: $message);
    }
}
