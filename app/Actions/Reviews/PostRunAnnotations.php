<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Reviews\Loggers\RunAnnotationActivityLogger;
use App\Actions\Reviews\Resolvers\RunAnnotationContextResolver;
use App\Actions\Reviews\ValueObjects\RunAnnotationContext;
use App\Models\Run;
use App\Services\Reviews\Builders\RunInlineCommentBuilder;
use App\Services\Reviews\Builders\RunReviewSummaryBuilder;
use App\Services\Reviews\PublishRunAnnotations;
use App\Services\Reviews\Resolvers\RunAnnotationsConfigResolver;
use App\Services\Reviews\StoreRunAnnotations;
use App\Services\Reviews\Strategies\EligibleFindingSelectionStrategy;
use Illuminate\Support\Facades\DB;

final readonly class PostRunAnnotations
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private RunAnnotationContextResolver $contextResolver,
        private RunAnnotationActivityLogger $activityLogger,
        private RunAnnotationsConfigResolver $annotationsConfigResolver,
        private EligibleFindingSelectionStrategy $eligibleFindingSelectionStrategy,
        private RunReviewSummaryBuilder $runReviewSummaryBuilder,
        private RunInlineCommentBuilder $runInlineCommentBuilder,
        private PublishRunAnnotations $publishRunAnnotations,
        private StoreRunAnnotations $storeRunAnnotations,
    ) {}

    /**
     * Post review annotations to GitHub as PR comments.
     */
    public function handle(Run $run): int
    {
        if ($run->findings()->whereHas('annotations')->exists()) {
            return 0;
        }

        $run->loadMissing(['repository.installation', 'findings', 'workspace']);

        $context = $this->contextResolver->resolve($run);
        if (! $context instanceof RunAnnotationContext) {
            return 0;
        }

        $annotationsConfig = $this->annotationsConfigResolver->resolve($run);
        $eligibleFindings = $this->eligibleFindingSelectionStrategy->select($run, $annotationsConfig);
        $reviewBody = $this->runReviewSummaryBuilder->build($run);

        if ($eligibleFindings->isEmpty()) {
            $this->publishRunAnnotations->postSummaryOnly(
                $run,
                $context->installationId,
                $context->owner,
                $context->repo,
                $context->pullRequestNumber,
                $reviewBody,
                $annotationsConfig
            );

            return 0;
        }

        $inlineComments = $this->runInlineCommentBuilder->build($eligibleFindings, $annotationsConfig);

        $metadata = $run->metadata ?? [];
        $commitId = is_string($metadata['head_sha'] ?? null) ? $metadata['head_sha'] : null;

        $reviewResponse = $this->publishRunAnnotations->handle(
            $run,
            $context->installationId,
            $context->owner,
            $context->repo,
            $context->pullRequestNumber,
            $reviewBody,
            $inlineComments,
            $commitId,
            $annotationsConfig
        );

        DB::transaction(function () use ($run, $eligibleFindings, $reviewResponse): void {
            $this->storeRunAnnotations->handle($run, $eligibleFindings, $reviewResponse);
        });

        $this->activityLogger->record($run, $eligibleFindings->count(), $context);

        return $eligibleFindings->count();
    }
}
