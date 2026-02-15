<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service for generating Sentinel's fun and friendly PR messages.
 */
final readonly class SentinelMessageService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private SentinelBrandingResolver $brandingResolver = new SentinelBrandingResolver,
        private SentinelStatusCommentBuilder $statusCommentBuilder = new SentinelStatusCommentBuilder,
    ) {}

    /**
     * Get a random greeting message for a new PR.
     *
     * @return array{emoji: string, message: string}
     */
    public function getRandomGreeting(): array
    {
        return $this->brandingResolver->getRandomGreeting();
    }

    /**
     * Get a random branding tagline.
     */
    public function getRandomBranding(): string
    {
        return $this->brandingResolver->getRandomBranding();
    }

    /**
     * Build the initial greeting comment with branding footer.
     */
    public function buildGreetingComment(): string
    {
        $greeting = $this->brandingResolver->getRandomGreeting();
        $branding = $this->brandingResolver->getRandomBranding();

        return <<<MARKDOWN
        {$greeting['emoji']} {$greeting['message']}

        ---
        <sub>{$branding}</sub>
        MARKDOWN;
    }

    /**
     * Build the review sign-off with view link.
     */
    public function buildReviewSignOff(string $runUrl): string
    {
        $branding = $this->brandingResolver->getRandomBranding();

        return <<<MARKDOWN

        ---
        📊 [View full analysis]({$runUrl})

        <sub>{$branding}</sub>
        MARKDOWN;
    }

    /**
     * Build a config error comment for a PR.
     */
    public function buildConfigErrorComment(string $error): string
    {
        return $this->statusCommentBuilder->buildConfigErrorComment($error);
    }

    /**
     * Build a comment explaining that review was skipped due to missing API keys.
     */
    public function buildNoProviderKeysComment(): string
    {
        return $this->statusCommentBuilder->buildNoProviderKeysComment();
    }

    /**
     * Build a comment explaining that the review run failed.
     */
    public function buildRunFailedComment(string $errorType): string
    {
        return $this->statusCommentBuilder->buildRunFailedComment($errorType);
    }

    /**
     * Build a comment explaining that auto-reviews are disabled.
     */
    public function buildAutoReviewDisabledComment(): string
    {
        return $this->statusCommentBuilder->buildAutoReviewDisabledComment();
    }

    /**
     * Build a comment explaining that the review was skipped due to plan limits.
     */
    public function buildPlanLimitReachedComment(?string $message): string
    {
        return $this->statusCommentBuilder->buildPlanLimitReachedComment($message);
    }

    /**
     * Build a comment explaining that the review was skipped because the repository is orphaned.
     */
    public function buildOrphanedRepositoryComment(): string
    {
        return $this->statusCommentBuilder->buildOrphanedRepositoryComment();
    }

    /**
     * Build a comment explaining that the review was skipped because the installation is inactive.
     */
    public function buildInstallationInactiveComment(): string
    {
        return $this->statusCommentBuilder->buildInstallationInactiveComment();
    }
}
