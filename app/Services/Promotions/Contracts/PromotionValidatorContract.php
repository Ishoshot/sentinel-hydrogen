<?php

declare(strict_types=1);

namespace App\Services\Promotions\Contracts;

use App\Services\Promotions\ValueObjects\PromotionValidationResult;

/**
 * Contract for promotion code validation.
 */
interface PromotionValidatorContract
{
    /**
     * Validate a promotion code.
     */
    public function validate(string $code): PromotionValidationResult;
}
