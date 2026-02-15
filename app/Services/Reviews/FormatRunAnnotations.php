<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Models\Finding;
use App\Models\Run;
use App\Services\Reviews\Builders\RunInlineCommentBuilder;
use App\Services\Reviews\Builders\RunReviewSummaryBuilder;
use App\Services\Reviews\Resolvers\RunAnnotationsConfigResolver;
use App\Services\Reviews\Strategies\EligibleFindingSelectionStrategy;
use Illuminate\Support\Collection;

final readonly class FormatRunAnnotations
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private RunAnnotationsConfigResolver $configResolver,
        private EligibleFindingSelectionStrategy $eligibleFindingSelector,
        private RunReviewSummaryBuilder $summaryBuilder,
        private RunInlineCommentBuilder $inlineCommentBuilder,
    ) {}

    /**
     * @return array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}
     */
    public function annotationsConfig(Run $run): array
    {
        return $this->configResolver->resolve($run);
    }

    /**
     * @param  array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}  $config
     * @return Collection<int, Finding>
     */
    public function filterEligibleFindings(Run $run, array $config): Collection
    {
        return $this->eligibleFindingSelector->select($run, $config);
    }

    /**
     * Build a markdown summary for the review result.
     */
    public function buildReviewSummary(Run $run): string
    {
        return $this->summaryBuilder->build($run);
    }

    /**
     * @param  Collection<int, Finding>  $findings
     * @param  array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}  $config
     * @return array<int, array{path: string, line: int, side: string, body: string, start_line: int, start_side: string}>
     */
    public function buildInlineComments(Collection $findings, array $config): array
    {
        return $this->inlineCommentBuilder->build($findings, $config);
    }
}
