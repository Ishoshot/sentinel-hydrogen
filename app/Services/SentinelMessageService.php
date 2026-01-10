<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Service for generating Sentinel's fun and friendly PR messages.
 */
final class SentinelMessageService
{
    private const CACHE_KEY = 'sentinel:messages';

    private const CACHE_TTL = 3600; // 1 hour

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
