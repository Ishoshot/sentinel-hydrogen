<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Selects random greeting and branding messages from the Sentinel message catalog.
 */
final class SentinelBrandingResolver
{
    public function __construct(
        private readonly SentinelMessageCatalogLoader $catalogLoader = new SentinelMessageCatalogLoader,
    ) {}

    /**
     * Get a random greeting message for a new PR.
     *
     * @return array{emoji: string, message: string}
     */
    public function getRandomGreeting(): array
    {
        $messages = $this->catalogLoader->load();

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
        $messages = $this->catalogLoader->load();

        /** @var non-empty-array<int, string> $branding */
        $branding = $messages['branding'];

        $index = random_int(0, count($branding) - 1);

        return $branding[$index];
    }
}
