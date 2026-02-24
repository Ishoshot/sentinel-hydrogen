<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\Reviews\AnnotationStyle;
use App\Models\Run;
use App\Services\Reviews\Publishers\PublishCheckRunAnnotations;
use App\Services\Reviews\Publishers\PublishCommentAnnotations;
use App\Services\Reviews\Publishers\PublishReviewAnnotations;
use App\Services\Reviews\ValueObjects\AnnotationConfig;

final readonly class PublishRunAnnotations
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private PublishReviewAnnotations $reviewAnnotationsPublisher,
        private PublishCommentAnnotations $commentAnnotationsPublisher,
        private PublishCheckRunAnnotations $checkRunAnnotationsPublisher,
    ) {}

    /**
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $inlineComments
     * @return array<string, mixed>
     */
    public function handle(
        Run $run,
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        string $reviewBody,
        array $inlineComments,
        ?string $commitId,
        AnnotationConfig $config,
    ): array {
        $style = AnnotationStyle::tryFrom($config->style) ?? AnnotationStyle::Review;

        return match ($style) {
            AnnotationStyle::Review => $this->reviewAnnotationsPublisher->publish(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $reviewBody,
                $inlineComments,
                $commitId,
                $config->grouped
            ),
            AnnotationStyle::Comment => $this->commentAnnotationsPublisher->publish(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $reviewBody,
                $inlineComments,
                $config->grouped
            ),
            AnnotationStyle::Check => $this->checkRunAnnotationsPublisher->publish(
                $run,
                $installationId,
                $owner,
                $repo,
                $reviewBody,
                $inlineComments,
                $commitId
            ),
        };
    }

    /**
     * Post only a summary comment (no inline annotations).
     */
    public function postSummaryOnly(
        Run $run,
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        string $summary,
        AnnotationConfig $config,
    ): void {
        $style = AnnotationStyle::tryFrom($config->style) ?? AnnotationStyle::Review;

        $metadata = $run->metadata ?? [];
        $commitId = is_string($metadata['head_sha'] ?? null) ? $metadata['head_sha'] : null;

        match ($style) {
            AnnotationStyle::Review => $this->reviewAnnotationsPublisher->postSummaryOnly(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $summary
            ),
            AnnotationStyle::Comment => $this->commentAnnotationsPublisher->postSummaryOnly(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $summary
            ),
            AnnotationStyle::Check => $this->checkRunAnnotationsPublisher->postSummaryOnly(
                $installationId,
                $owner,
                $repo,
                $summary,
                $commitId
            ),
        };
    }
}
