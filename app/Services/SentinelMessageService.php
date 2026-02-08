<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\SentinelMessageServiceContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Service for generating Sentinel's fun and friendly PR messages.
 */
final class SentinelMessageService implements SentinelMessageServiceContract
{
    private const string CACHE_KEY = 'sentinel:messages';

    private const int CACHE_TTL = 3600; // 1 hour

    /**
     * @var array{greetings: array<int, array{emoji: string, message: string}>, branding: array<int, string>}|null
     */
    private ?array $messages = null;

    /**
     * Get a random greeting message for a new PR.
     *
     * @return array{emoji: string, message: string}
     */
    public function getRandomGreeting(): array
    {
        $messages = $this->loadMessages();

        /** @var non-empty-array<int, array{emoji: string, message: string}> $greetings */
        $greetings = $messages['greetings'];

        $index = random_int(0, count($greetings) - 1);

        return $greetings[$index];
    }

    /**
     * Get a random branding tagline.
     */
    public function getRandomBranding(): string
    {
        $messages = $this->loadMessages();

        /** @var non-empty-array<int, string> $branding */
        $branding = $messages['branding'];

        $index = random_int(0, count($branding) - 1);

        return $branding[$index];
    }

    /**
     * Build the initial greeting comment with branding footer.
     */
    public function buildGreetingComment(): string
    {
        $greeting = $this->getRandomGreeting();
        $branding = $this->getRandomBranding();

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
        $branding = $this->getRandomBranding();

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
        $branding = $this->getRandomBranding();

        return <<<MARKDOWN
        ⚠️ **Sentinel Configuration Error**

        Your `.sentinel/config.yaml` file contains an error:

        ```
        {$error}
        ```

        Review has been skipped until this is resolved. Please fix the configuration and push again.

        📖 [Configuration documentation](https://docs.useSentinel.com/configuration)

        ---
        <sub>{$branding}</sub>
        MARKDOWN;
    }

    /**
     * Build a comment explaining that review was skipped due to missing API keys.
     */
    public function buildNoProviderKeysComment(): string
    {
        $branding = $this->getRandomBranding();

        return <<<MARKDOWN
        ⚠️ **Review Skipped - No API Key Configured**

        Sentinel cannot perform a code review because no AI provider API key has been configured for this repository.

        **To enable reviews:**
        1. Go to your repository settings in the Sentinel dashboard
        2. Navigate to **API Keys**
        3. Add your Anthropic or OpenAI API key

        Your API key is encrypted and never exposed after saving.

        ---
        <sub>{$branding}</sub>
        MARKDOWN;
    }

    /**
     * Build a comment explaining that the review run failed.
     */
    public function buildRunFailedComment(string $errorType): string
    {
        $branding = $this->getRandomBranding();

        return <<<MARKDOWN
        ❌ **Review Failed**

        Sentinel encountered an error while reviewing this pull request.

        **Error Type:** `{$errorType}`

        This has been logged and will be investigated. You can try:
        - Pushing a new commit to trigger a new review
        - Checking your repository settings in the Sentinel dashboard

        If the issue persists, please contact support.

        ---
        <sub>{$branding}</sub>
        MARKDOWN;
    }

    /**
     * Build a comment explaining that auto-reviews are disabled.
     */
    public function buildAutoReviewDisabledComment(): string
    {
        $branding = $this->getRandomBranding();

        return <<<MARKDOWN
        > [!IMPORTANT]
        > ## Review skipped
        >
        > Auto reviews are disabled on this repository.
        >
        > Please check the settings in the Sentinel UI or the `.sentinel/config.yaml` file in this repository. To trigger a single review, invoke the `@sentinel review` command.
        >
        > You can disable this status message by setting the `reviews.review_status` to `false` in the Sentinel configuration file.

        ---
        <sub>{$branding}</sub>
        MARKDOWN;
    }

    /**
     * Build a comment explaining that the review was skipped due to plan limits.
     */
    public function buildPlanLimitReachedComment(?string $message): string
    {
        $branding = $this->getRandomBranding();
        $details = $message ?? 'Your current plan has reached its limit.';

        return <<<MARKDOWN
        ⚠️ **Review Skipped - Plan Limit Reached**

        {$details}

        Upgrade your plan in the Sentinel dashboard to continue running reviews.

        ---
        <sub>{$branding}</sub>
        MARKDOWN;
    }

    /**
     * Build a comment explaining that the review was skipped because the repository is orphaned.
     */
    public function buildOrphanedRepositoryComment(): string
    {
        $branding = $this->getRandomBranding();

        return <<<MARKDOWN
        ⚠️ **Review Skipped - Repository Not Connected**

        This repository is not associated with any Sentinel workspace. Reviews cannot be performed without a workspace connection.

        **To fix this:**
        1. Go to your Sentinel dashboard
        2. Navigate to **Repositories**
        3. Re-connect this repository to your workspace

        ---
        <sub>{$branding}</sub>
        MARKDOWN;
    }

    /**
     * Build a comment explaining that the review was skipped because the installation is inactive.
     */
    public function buildInstallationInactiveComment(): string
    {
        $branding = $this->getRandomBranding();

        return <<<MARKDOWN
        ⚠️ **Review Skipped - Installation Inactive**

        The GitHub App installation for this repository is no longer active. Reviews cannot be performed without an active installation.

        **To fix this:**
        1. Go to your GitHub organization/account settings
        2. Navigate to **Installed GitHub Apps**
        3. Re-install or re-activate the Sentinel GitHub App

        ---
        <sub>{$branding}</sub>
        MARKDOWN;
    }

    /**
     * Load messages from JSON file with caching.
     *
     * @return array{greetings: array<int, array{emoji: string, message: string}>, branding: array<int, string>}
     */
    private function loadMessages(): array
    {
        if ($this->messages !== null) {
            return $this->messages;
        }

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        // Validate cache has required keys, otherwise reload from file
        if ($cached !== null && $this->isValidMessageStructure($cached)) {
            /** @var array{greetings: array<int, array{emoji: string, message: string}>, branding: array<int, string>} $cached */
            $this->messages = $cached;

            return $cached;
        }

        $path = resource_path('messages/greetings.json');
        $content = File::get($path);

        /** @var array{greetings: array<int, array{emoji: string, message: string}>, branding: array<int, string>} $messages */
        $messages = json_decode($content, true);

        Cache::put(self::CACHE_KEY, $messages, self::CACHE_TTL);

        $this->messages = $messages;

        return $messages;
    }

    /**
     * Check if the cached message structure has all required keys.
     *
     * @param  array<string, mixed>  $data
     */
    private function isValidMessageStructure(array $data): bool
    {
        return isset($data['greetings'], $data['branding'])
            && is_array($data['greetings'])
            && is_array($data['branding']);
    }
}
