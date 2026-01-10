<?php

declare(strict_types=1);

namespace App\Services\Context\Contracts;

use App\Services\Context\ContextBag;

/**
 * Interface for context filters that refine or truncate context data.
 */
interface ContextFilter
{
    /**
     * Unique identifier for this filter.
     */
    public function name(): string;

    /**
     * Order in which filters run (lower = runs first).
     */
    public function order(): int;

    /**
     * Filter/transform the context bag.
     */
    public function filter(ContextBag $bag): void;
}
