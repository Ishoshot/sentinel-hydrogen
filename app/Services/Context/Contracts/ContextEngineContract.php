<?php

declare(strict_types=1);

namespace App\Services\Context\Contracts;

use App\Services\Context\ContextBag;

/**
 * Contract for the context engine that orchestrates context collection.
 */
interface ContextEngineContract
{
    /**
     * Build complete context for a review.
     *
     * @param  array<string, mixed>  $params
     */
    public function build(array $params): ContextBag;

    /**
     * Register a context collector.
     */
    public function registerCollector(ContextCollector $collector): self;

    /**
     * Register a context filter.
     */
    public function registerFilter(ContextFilter $filter): self;

    /**
     * Get all registered collector names.
     *
     * @return array<string>
     */
    public function getCollectorNames(): array;

    /**
     * Get all registered filter names.
     *
     * @return array<string>
     */
    public function getFilterNames(): array;
}
