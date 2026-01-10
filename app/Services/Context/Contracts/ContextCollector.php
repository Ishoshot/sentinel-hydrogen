<?php

declare(strict_types=1);

namespace App\Services\Context\Contracts;

use App\Services\Context\ContextBag;

/**
 * Interface for context collectors that gather data from external sources.
 */
interface ContextCollector
{
    /**
     * Unique identifier for this collector.
     */
    public function name(): string;

    /**
     * Priority level (higher = collected first, kept if truncation needed).
     */
    public function priority(): int;

    /**
     * Collect context and add to the bag.
     *
     * @param  array<string, mixed>  $params
     */
    public function collect(ContextBag $bag, array $params): void;

    /**
     * Check if this collector should run for the given params.
     *
     * @param  array<string, mixed>  $params
     */
    public function shouldCollect(array $params): bool;
}
