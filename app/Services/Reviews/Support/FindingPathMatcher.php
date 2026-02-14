<?php

declare(strict_types=1);

namespace App\Services\Reviews\Support;

/**
 * Matches file paths against glob patterns for finding filtering.
 */
final readonly class FindingPathMatcher
{
    /**
     * Check if a path matches any of the given glob patterns.
     *
     * @param  array<string>  $patterns
     */
    public function matchesAny(string $path, array $patterns): bool
    {
        if ($patterns === []) {
            return false;
        }

        return array_any($patterns, fn (string $pattern): bool => $this->matchesGlob($path, $pattern));
    }

    /**
     * Evaluate whether a path matches a single glob expression.
     */
    private function matchesGlob(string $path, string $pattern): bool
    {
        if ($path === $pattern) {
            return true;
        }

        return preg_match($this->globToRegex($pattern), $path) === 1;
    }

    /**
     * Convert a glob expression into a regular expression.
     */
    private function globToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        $escaped = str_replace('\\*\\*', '.*', $escaped);
        $escaped = str_replace('\\*', '[^\/]*', $escaped);
        $escaped = str_replace('\\?', '[^\/]', $escaped);

        return '/^'.$escaped.'$/';
    }
}
