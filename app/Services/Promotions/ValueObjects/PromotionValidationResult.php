<?php

declare(strict_types=1);

namespace App\Services\Promotions\ValueObjects;

use App\Models\Promotion;

/**
 * Result object for promotion validation.
 */
final readonly class PromotionValidationResult
{
    /**
     * Create a new validation result.
     */
    private function __construct(
        public bool $valid,
        public ?Promotion $promotion,
        public ?string $message,
    ) {}

    /**
     * Create a successful validation result.
     */
    public static function success(Promotion $promotion): self
    {
        return new self(
            valid: true,
            promotion: $promotion,
            message: null,
        );
    }

    /**
     * Create a failed validation result.
     */
    public static function failure(string $message): self
    {
        return new self(
            valid: false,
            promotion: null,
            message: $message,
        );
    }

    /**
     * Check if the validation was successful.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Check if the validation failed.
     */
    public function failed(): bool
    {
        return ! $this->valid;
    }
}
