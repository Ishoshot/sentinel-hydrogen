<?php

declare(strict_types=1);

namespace App\Actions\Reviews\ValueObjects;

final readonly class ManualReviewPullRequestFetchResult
{
    /**
     * Create a new pull request fetch result.
     *
     * @param  array<string, mixed>  $pullRequestData
     */
    public function __construct(
        public bool $successful,
        public array $pullRequestData = [],
        public ?string $message = null,
    ) {}

    /**
     * Create a successful fetch result.
     *
     * @param  array<string, mixed>  $pullRequestData
     */
    public static function success(array $pullRequestData): self
    {
        return new self(successful: true, pullRequestData: $pullRequestData);
    }

    /**
     * Create a failed fetch result.
     */
    public static function failure(string $message): self
    {
        return new self(successful: false, message: $message);
    }
}
