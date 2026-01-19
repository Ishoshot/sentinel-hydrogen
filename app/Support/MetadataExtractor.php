<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Utility class for safely extracting typed values from metadata arrays.
 *
 * Provides type-safe extraction methods for common patterns used when
 * processing webhook payloads and API responses.
 */
final readonly class MetadataExtractor
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data) {}

    /**
     * Create a new extractor from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self($data);
    }

    /**
     * Get a string value with a default fallback.
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * Get a nullable string value.
     */
    public function stringOrNull(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Get an integer value with a default fallback.
     */
    public function int(string $key, int $default = 0): int
    {
        $value = $this->data[$key] ?? $default;

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get a boolean value with a default fallback.
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->data[$key] ?? $default;

        return is_bool($value) ? $value : $default;
    }

    /**
     * Get an array value with a default fallback.
     *
     * @param  array<mixed>  $default
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->data[$key] ?? $default;

        return is_array($value) ? $value : $default;
    }

    /**
     * Extract author data from metadata with fallback to sender_login.
     *
     * @return array{login: string, avatar_url: string|null}
     */
    public function author(string $key = 'author', string $fallbackLoginKey = 'sender_login'): array
    {
        $author = $this->data[$key] ?? null;
        $fallbackLogin = $this->string($fallbackLoginKey);

        if (is_array($author) && isset($author['login']) && is_string($author['login'])) {
            return [
                'login' => $author['login'],
                'avatar_url' => isset($author['avatar_url']) && is_string($author['avatar_url'])
                    ? $author['avatar_url']
                    : null,
            ];
        }

        return [
            'login' => $fallbackLogin,
            'avatar_url' => null,
        ];
    }

    /**
     * Extract an array of user objects (for assignees, reviewers).
     *
     * @return array<int, array{login: string, avatar_url: string|null}>
     */
    public function users(string $key): array
    {
        $users = $this->data[$key] ?? [];

        if (! is_array($users)) {
            return [];
        }

        $result = [];
        foreach ($users as $user) {
            if (is_array($user) && isset($user['login']) && is_string($user['login'])) {
                $result[] = [
                    'login' => $user['login'],
                    'avatar_url' => isset($user['avatar_url']) && is_string($user['avatar_url'])
                        ? $user['avatar_url']
                        : null,
                ];
            }
        }

        return $result;
    }

    /**
     * Extract an array of label objects.
     *
     * @return array<int, array{name: string, color: string}>
     */
    public function labels(string $key = 'labels'): array
    {
        $labels = $this->data[$key] ?? [];

        if (! is_array($labels)) {
            return [];
        }

        $result = [];
        foreach ($labels as $label) {
            if (is_array($label) && isset($label['name']) && is_string($label['name'])) {
                $result[] = [
                    'name' => $label['name'],
                    'color' => isset($label['color']) && is_string($label['color'])
                        ? $label['color']
                        : 'cccccc',
                ];
            }
        }

        return $result;
    }
}
