<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Normalizes import module names across 33+ languages for dependency matching.
 */
final readonly class ModuleNameNormalizer
{
    /**
     * Rust standard library modules to filter out.
     *
     * @var array<int, string>
     */
    private const array RUST_STD_MODULES = ['std', 'core', 'alloc', 'self', 'super', 'crate'];

    /**
     * Standard library roots to filter out when normalizing imports.
     *
     * @var array<int, string>
     */
    private const array STD_LIB_ROOTS = [
        'java', 'javax', 'sun', 'com.sun',
        'System', 'Microsoft',
        'os', 'sys', 'io', 're', 'json', 'typing', 'collections', 'functools', 'itertools',
        'Kernel', 'Enum', 'List', 'Map', 'String', 'IO', 'File',
    ];

    /**
     * Normalize a module name to match against dependencies.
     *
     * Returns null if the module is internal (not from a package).
     */
    public function normalize(string $module): ?string
    {
        // === PHP-specific handling ===
        if (str_starts_with($module, 'App\\') || str_starts_with($module, 'Tests\\')) {
            return null;
        }

        if (str_starts_with($module, 'Illuminate\\')) {
            return 'laravel/framework';
        }

        if (str_starts_with($module, 'Symfony\\')) {
            return 'Symfony';
        }

        if (str_contains($module, '\\')) {
            return explode('\\', $module)[0];
        }

        // === Dart-specific handling ===
        if (str_starts_with($module, 'package:')) {
            $withoutPrefix = mb_substr($module, 8);
            $parts = explode('/', $withoutPrefix);

            return $parts[0];
        }

        // === Rust/Perl/R-specific handling ===
        if (str_contains($module, '::')) {
            $root = explode('::', $module)[0];

            return in_array($root, self::RUST_STD_MODULES, true) ? null : $root;
        }

        // === Clojure-specific handling ===
        if (str_contains($module, '/') && ! preg_match('/^[a-z]+\.[a-z]+\//', $module)) {
            $namespace = explode('/', $module)[0];

            if (str_contains($namespace, '.')) {
                return explode('.', $namespace)[0];
            }

            return $namespace;
        }

        // === Go-specific handling ===
        if (preg_match('/^[a-z]+\.[a-z]+\//', $module)) {
            return $module;
        }

        // === Python/Java/C#/Scala/Kotlin/Elixir/Haskell/OCaml/Julia/Lua ===
        if (str_contains($module, '.')) {
            $root = explode('.', $module)[0];

            return in_array($root, self::STD_LIB_ROOTS, true) ? null : $root;
        }

        // === Simple names (Ruby, Swift, JS, etc.) ===
        return $module;
    }
}
