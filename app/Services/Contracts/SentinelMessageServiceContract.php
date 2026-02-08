<?php

declare(strict_types=1);

namespace App\Services\Contracts;

/**
 * Contract for generating Sentinel's PR messages.
 */
interface SentinelMessageServiceContract
{
    /**
     * Get a random greeting message for a new PR.
     *
     * @return array{emoji: string, message: string}
     */
    public function getRandomGreeting(): array;

    /**
     * Get a random branding tagline.
     */
    public function getRandomBranding(): string;

    /**
     * Build the initial greeting comment with branding footer.
     */
    public function buildGreetingComment(): string;

    /**
     * Build the review sign-off with view link.
     */
    public function buildReviewSignOff(string $runUrl): string;

    /**
     * Build a config error comment for a PR.
     */
    public function buildConfigErrorComment(string $error): string;

    /**
     * Build a comment explaining that review was skipped due to missing API keys.
     */
    public function buildNoProviderKeysComment(): string;

    /**
     * Build a comment explaining that the review run failed.
     */
    public function buildRunFailedComment(string $errorType): string;

    /**
     * Build a comment explaining that auto-reviews are disabled.
     */
    public function buildAutoReviewDisabledComment(): string;

    /**
     * Build a comment explaining that the review was skipped due to plan limits.
     */
    public function buildPlanLimitReachedComment(?string $message): string;

    /**
     * Build a comment explaining that the review was skipped because the repository is orphaned.
     */
    public function buildOrphanedRepositoryComment(): string;

    /**
     * Build a comment explaining that the review was skipped because the installation is inactive.
     */
    public function buildInstallationInactiveComment(): string;
}
