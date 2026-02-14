<?php

declare(strict_types=1);

namespace App\Services\Context\Support;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\Context\Contracts\ContextFilter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Executes collector and filter pipelines with sorting and error handling.
 */
final readonly class ContextPipelineRunner
{
    /**
     * Run all collectors in priority order (highest first).
     *
     * @param  array<string, ContextCollector>  $collectors
     * @param  array<string, mixed>  $params
     */
    public function runCollectors(array $collectors, ContextBag $bag, array $params): void
    {
        $sorted = $this->sortCollectors($collectors);

        foreach ($sorted as $collector) {
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
     *
     * @param  array<string, ContextFilter>  $filters
     */
    public function runFilters(array $filters, ContextBag $bag): void
    {
        $sorted = $this->sortFilters($filters);

        foreach ($sorted as $filter) {
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
     * @param  array<string, ContextCollector>  $collectors
     * @return array<ContextCollector>
     */
    private function sortCollectors(array $collectors): array
    {
        $sorted = array_values($collectors);

        usort($sorted, fn (ContextCollector $a, ContextCollector $b): int => $b->priority() <=> $a->priority());

        return $sorted;
    }

    /**
     * Get filters sorted by order (lowest first).
     *
     * @param  array<string, ContextFilter>  $filters
     * @return array<ContextFilter>
     */
    private function sortFilters(array $filters): array
    {
        $sorted = array_values($filters);

        usort($sorted, fn (ContextFilter $a, ContextFilter $b): int => $a->order() <=> $b->order());

        return $sorted;
    }
}
