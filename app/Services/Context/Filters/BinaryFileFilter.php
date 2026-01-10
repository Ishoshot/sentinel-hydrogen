<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;

/**
 * Filters out binary files, lock files, and large generated files.
 */
final class BinaryFileFilter implements ContextFilter
{
    /**
     * File extensions to exclude (binary/generated).
     *
     * @var array<string>
     */
    private const array EXCLUDED_EXTENSIONS = [
        // Binary files
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'svg', 'bmp', 'tiff',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'tar', 'gz', 'rar', '7z',
        'exe', 'dll', 'so', 'dylib',
        'woff', 'woff2', 'ttf', 'eot', 'otf',
        'mp3', 'mp4', 'wav', 'avi', 'mov',
        // Lock files
        'lock',
        // Minified files
        'min.js', 'min.css',
        // Source maps
        'map',
    ];

    /**
     * Exact filenames to exclude.
     *
     * @var array<string>
     */
    private const array EXCLUDED_FILENAMES = [
        'package-lock.json',
        'composer.lock',
        'yarn.lock',
        'pnpm-lock.yaml',
        'Cargo.lock',
        'Gemfile.lock',
        'poetry.lock',
        '.DS_Store',
        'Thumbs.db',
    ];

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'binary_file';
    }

    /**
     * {@inheritdoc}
     */
    public function order(): int
    {
        return 20; // Run after vendor path filter
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

        // Recalculate metrics after filtering
        $bag->metrics = [
            'files_changed' => count($bag->files),
            'lines_added' => array_sum(array_column($bag->files, 'additions')),
            'lines_deleted' => array_sum(array_column($bag->files, 'deletions')),
        ];
    }

    /**
     * Check if a file should be excluded based on extension or filename.
     */
    private function shouldExclude(string $path): bool
    {
        $filename = basename($path);

        // Check exact filename matches
        if (in_array($filename, self::EXCLUDED_FILENAMES, true)) {
            return true;
        }

        // Check extension matches
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, self::EXCLUDED_EXTENSIONS, true)) {
            return true;
        }

        // Check compound extensions (e.g., .min.js)
        return str_ends_with(mb_strtolower($path), '.min.js') || str_ends_with(mb_strtolower($path), '.min.css');
    }
}
