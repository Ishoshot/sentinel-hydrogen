<?php

declare(strict_types=1);

namespace App\Services\Contracts;

/**
 * Contract for enforcement/validation result objects.
 *
 * Provides a consistent interface for checking whether an operation
 * was allowed or denied across different enforcement services.
 */
interface EnforcementResult
{
    /**
     * Check if the operation was allowed.
     */
    public function isAllowed(): bool;

    /**
     * Check if the operation was denied.
     */
    public function isDenied(): bool;

    /**
     * Get the denial reason or message, if any.
     */
    public function getMessage(): ?string;
}
