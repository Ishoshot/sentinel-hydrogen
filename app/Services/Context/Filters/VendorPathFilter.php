<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;

/**
 * Filters out files from vendor directories and generated paths.
 */
final class VendorPathFilter implements ContextFilter
{
    /**
     * Patterns for paths to exclude from context.
     *
     * @var array<string>
     */
    private const array EXCLUDED_PATTERNS = [
        '/^vendor\//',
        '/^node_modules\//',
        '/^\.git\//',
        '/^storage\//',
        '/^bootstrap\/cache\//',
        '/^public\/build\//',
        '/^public\/vendor\//',
        '/^dist\//',
        '/^build\//',
        '/^\.next\//',
        '/^\.nuxt\//',
    ];

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'vendor_path';
    }

    /**
     * {@inheritdoc}
     */
    public function order(): int
    {
        return 10; // Run early to remove noise before other processing
    }

    /**
     * {@inheritdoc}
     */
    public function filter(ContextBag $bag): void
    {
        $bag->files = array_values(array_filter(
            $bag->files,
            fn (array $file): bool => ! $this->shouldExclude($file['filename'])
        ));

        $bag->recalculateMetrics();
    }

    /**
     * Check if a file path should be excluded.
     */
    private function shouldExclude(string $path): bool
    {
        return array_any(self::EXCLUDED_PATTERNS, static fn (string $pattern): bool => preg_match($pattern, $path) === 1);
    }
}
