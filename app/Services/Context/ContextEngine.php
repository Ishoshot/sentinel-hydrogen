<?php

declare(strict_types=1);

namespace App\Services\Context;

use App\Services\Context\Contracts\ContextCollector;
use App\Services\Context\Contracts\ContextEngineContract;
use App\Services\Context\Contracts\ContextFilter;
use App\Services\Context\Support\ContextPipelineRunner;
use Illuminate\Support\Facades\Log;

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
     * Create a new context engine instance.
     */
    public function __construct(
        private readonly ContextPipelineRunner $pipelineRunner = new ContextPipelineRunner,
    ) {}

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

        $this->pipelineRunner->runCollectors($this->collectors, $bag, $params);
        $this->pipelineRunner->runFilters($this->filters, $bag);

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
}
