<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

final readonly class PullRequestWebhookPreflightResult
{
    /**
     * Create a new preflight result instance.
     */
    public function __construct(
        public ?string $skipReason = null,
        public ?string $configErrorMessage = null,
        public bool $shouldPostAutoReviewDisabledComment = false,
    ) {}

    /**
     * Create a result that allows review execution.
     */
    public static function proceed(): self
    {
        return new self;
    }

    /**
     * Create a result for repositories with auto-review disabled.
     */
    public static function autoReviewDisabled(): self
    {
        return new self(shouldPostAutoReviewDisabledComment: true);
    }

    /**
     * Create a result for repository configuration errors.
     */
    public static function configError(string $message): self
    {
        return new self(configErrorMessage: $message);
    }

    /**
     * Create a result for trigger-rule skips.
     */
    public static function triggerSkipped(string $reason): self
    {
        return new self(skipReason: $reason);
    }
}
