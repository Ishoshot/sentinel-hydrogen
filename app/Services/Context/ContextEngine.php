<?php

declare(strict_types=1);

namespace App\Services\Context;

use App\Services\Context\Contracts\ContextCollector;
use App\Services\Context\Contracts\ContextEngineContract;
use App\Services\Context\Contracts\ContextFilter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates context collection and filtering for code reviews.
 */
final class ContextEngine implements ContextEngineContract
{
    /** @var array<string, ContextCollector> */
    private array $collectors = [];

    /** @var array<string, ContextFilter> */
    private array $filters = [];

    /**
     * Register a context collector.
     */
    public function registerCollector(ContextCollector $collector): self
    {
        $this->collectors[$collector->name()] = $collector;

        return $this;
    }

    /**
     * Register a context filter.
     */
    public function registerFilter(ContextFilter $filter): self
    {
        $this->filters[$filter->name()] = $filter;

        return $this;
    }

    /**
     * Build complete context for a review.
     *
     * @param  array<string, mixed>  $params
     */
    public function build(array $params): ContextBag
    {
        $bag = new ContextBag();

        $this->runCollectors($bag, $params);
        $this->runFilters($bag);

        Log::debug('Context engine built context', [
            'estimated_tokens' => $bag->estimateTokens(),
            'files_with_patches' => $bag->getFilesWithPatchCount(),
            'linked_issues_count' => count($bag->linkedIssues),
            'pr_comments_count' => count($bag->prComments),
        ]);

        return $bag;
    }

    /**
     * Get all registered collector names.
     *
     * @return array<string>
     */
    public function getCollectorNames(): array
    {
        return array_keys($this->collectors);
    }

    /**
     * Get all registered filter names.
     *
     * @return array<string>
     */
    public function getFilterNames(): array
    {
        return array_keys($this->filters);
    }

    /**
     * Run all collectors in priority order (highest first).
     *
     * @param  array<string, mixed>  $params
     */
    private function runCollectors(ContextBag $bag, array $params): void
    {
        $collectors = $this->getSortedCollectors();

        foreach ($collectors as $collector) {
            if (! $collector->shouldCollect($params)) {
                Log::debug('Skipping collector', ['collector' => $collector->name()]);

                continue;
            }

            try {
                $collector->collect($bag, $params);
                Log::debug('Collector completed', ['collector' => $collector->name()]);
            } catch (Throwable $e) {
                Log::warning('Collector failed', [
                    'collector' => $collector->name(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Run all filters in order (lowest order value first).
     */
    private function runFilters(ContextBag $bag): void
    {
        $filters = $this->getSortedFilters();

        foreach ($filters as $filter) {
            try {
                $filter->filter($bag);
                Log::debug('Filter completed', ['filter' => $filter->name()]);
            } catch (Throwable $e) {
                Log::warning('Filter failed', [
                    'filter' => $filter->name(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get collectors sorted by priority (highest first).
     *
     * @return array<ContextCollector>
     */
    private function getSortedCollectors(): array
    {
        $collectors = array_values($this->collectors);

        usort($collectors, fn (ContextCollector $a, ContextCollector $b): int => $b->priority() <=> $a->priority());

        return $collectors;
    }

    /**
     * Get filters sorted by order (lowest first).
     *
     * @return array<ContextFilter>
     */
    private function getSortedFilters(): array
    {
        $filters = array_values($this->filters);

        usort($filters, fn (ContextFilter $a, ContextFilter $b): int => $a->order() <=> $b->order());

        return $filters;
    }
}
