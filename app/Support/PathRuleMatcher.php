<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Matches paths against glob-style rules.
 */
final readonly class PathRuleMatcher
{
    /**
     * Determine if a path matches any of the provided patterns.
     *
     * @param  array<int, string>  $patterns
     */
    public function matchesAny(string $path, array $patterns): bool
    {
        return array_any($patterns, fn (string $pattern): bool => $this->matchesGlob($path, $pattern));
    }

    /**
     * Determine if a path matches a glob pattern.
     */
    public function matchesGlob(string $path, string $pattern): bool
    {
        if ($path === $pattern) {
            return true;
        }

        $regex = $this->globToRegex($pattern);

        return preg_match($regex, $path) === 1;
    }

    /**
     * Convert a glob pattern to a regex.
     */
    private function globToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        $escaped = str_replace('\*\*', '.*', $escaped);
        $escaped = str_replace('\*', '[^\/]*', $escaped);
        $escaped = str_replace('\?', '[^\/]', $escaped);

        return '/^'.$escaped.'$/';
    }
}
