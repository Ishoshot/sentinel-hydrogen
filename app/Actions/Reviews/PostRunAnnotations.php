<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Reviews\Loggers\RunAnnotationActivityLogger;
use App\Actions\Reviews\Resolvers\RunAnnotationContextResolver;
use App\Actions\Reviews\ValueObjects\RunAnnotationContext;
use App\Models\Run;
use App\Services\Reviews\FormatRunAnnotations;
use App\Services\Reviews\PublishRunAnnotations;
use App\Services\Reviews\StoreRunAnnotations;
use Illuminate\Support\Facades\DB;

final readonly class PostRunAnnotations
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private RunAnnotationContextResolver $contextResolver,
        private RunAnnotationActivityLogger $activityLogger,
        private FormatRunAnnotations $formatRunAnnotations,
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

        $annotationsConfig = $this->formatRunAnnotations->annotationsConfig($run);
        $eligibleFindings = $this->formatRunAnnotations->filterEligibleFindings($run, $annotationsConfig);
        $reviewBody = $this->formatRunAnnotations->buildReviewSummary($run);

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

        $inlineComments = $this->formatRunAnnotations->buildInlineComments($eligibleFindings, $annotationsConfig);

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
