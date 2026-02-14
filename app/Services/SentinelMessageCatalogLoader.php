<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

final class SentinelMessageCatalogLoader
{
    private const string CACHE_KEY = 'sentinel:messages';

    private const int CACHE_TTL = 3600;

    /**
     * @var array{greetings: array<int, array{emoji: string, message: string}>, branding: array<int, string>}|null
     */
    private ?array $messages = null;

    /**
     * @return array{greetings: array<int, array{emoji: string, message: string}>, branding: array<int, string>}
     */
    public function load(): array
    {
        if ($this->messages !== null) {
            return $this->messages;
        }

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null && $this->hasValidStructure($cached)) {
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
     * @param  array<string, mixed>  $data
     */
    private function hasValidStructure(array $data): bool
    {
        return isset($data['greetings'], $data['branding'])
            && is_array($data['greetings'])
            && is_array($data['branding']);
    }
}
