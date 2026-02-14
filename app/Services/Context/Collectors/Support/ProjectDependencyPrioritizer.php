<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Prioritizes and limits dependencies based on semantic import usage.
 */
final class ProjectDependencyPrioritizer
{
    private const int MAX_MAIN_DEPENDENCIES = 50;

    private const int MAX_DEV_DEPENDENCIES = 20;

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
     * Deduplicate an array of items by name.
     *
     * @param  array<int, array{name: string, version: string}>  $items
     * @return array<int, array{name: string, version: string}>
     */
    public function deduplicateByName(array $items): array
    {
        $seen = [];
        $result = [];

        foreach ($items as $item) {
            if (! isset($seen[$item['name']])) {
                $seen[$item['name']] = true;
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Extract imported module names from semantic analysis data.
     *
     * @param  array<string, array<string, mixed>>  $semantics
     * @return array<string>
     */
    public function extractImportedModules(array $semantics): array
    {
        $modules = [];

        foreach ($semantics as $data) {
            $imports = $data['imports'] ?? [];

            if (! is_array($imports)) {
                continue;
            }

            foreach ($imports as $import) {
                if (isset($import['module']) && is_string($import['module'])) {
                    $module = $import['module'];

                    // Normalize module names for matching
                    // PHP: "App\Services\Example" -> ignore internal, keep packages like "Illuminate\Support\Facades\Log"
                    // JS: "@angular/core" stays as-is, "react" stays as-is
                    // Python: "django.http" -> "django"
                    // Go: "github.com/gin-gonic/gin" stays as-is
                    $normalized = $this->normalizeModuleName($module);

                    if ($normalized !== null) {
                        $modules[] = $normalized;
                    }
                }
            }
        }

        return array_unique($modules);
    }

    /**
     * Limit dependencies to a reasonable number, prioritizing those actually used.
     *
     * @param  array<int, array{name: string, version: string, dev?: bool}>  $dependencies
     * @param  array<string>  $importedModules
     * @return array<int, array{name: string, version: string, dev?: bool}>
     */
    public function limitDependencies(array $dependencies, array $importedModules = []): array
    {
        $main = [];
        $dev = [];

        foreach ($dependencies as $dep) {
            if (isset($dep['dev']) && $dep['dev']) {
                $dev[] = $dep;
            } else {
                $main[] = $dep;
            }
        }

        // If we have imported modules, prioritize dependencies that are used
        if ($importedModules !== []) {
            $main = $this->sortByUsage($main, $importedModules);
            $dev = $this->sortByUsage($dev, $importedModules);
        }

        $main = array_slice($main, 0, self::MAX_MAIN_DEPENDENCIES);
        $dev = array_slice($dev, 0, self::MAX_DEV_DEPENDENCIES);

        return array_merge($main, $dev);
    }

    /**
     * Normalize a module name to match against dependencies.
     *
     * Handles import patterns from all 33+ supported languages:
     * - PHP: Backslash namespaces (Illuminate\Support\Facades\Log)
     * - Python/Java/C#/Scala/Kotlin/Elixir/Haskell/OCaml/Julia/Lua: Dot notation (django.http)
     * - Rust/Perl/R: Double colon (tokio::sync::mpsc, dplyr::filter)
     * - Go: Full URL paths (github.com/gin-gonic/gin)
     * - JavaScript/TypeScript: Simple names or scoped (@angular/core)
     * - Ruby/Swift/Objective-C: Simple names
     * - Clojure: Slash for qualified symbols (clojure.core/map)
     * - Dart: package: prefix (package:flutter/material.dart)
     * - C/C++: Header includes (usually not useful for dependency matching)
     *
     * Returns null if the module is internal (not from a package).
     */
    private function normalizeModuleName(string $module): ?string
    {
        // === PHP-specific handling ===
        // Skip PHP internal namespaces (App\, Tests\)
        if (str_starts_with($module, 'App\\') || str_starts_with($module, 'Tests\\')) {
            return null;
        }

        // PHP: Map Illuminate namespace to Laravel
        if (str_starts_with($module, 'Illuminate\\')) {
            return 'laravel/framework';
        }

        // PHP: Symfony namespace
        if (str_starts_with($module, 'Symfony\\')) {
            return 'Symfony';
        }

        // PHP namespaces (backslash separator)
        // GuzzleHttp\Client -> GuzzleHttp, Monolog\Logger -> Monolog
        if (str_contains($module, '\\')) {
            return explode('\\', $module)[0];
        }

        // === Dart-specific handling ===
        // Dart: package:flutter/material.dart -> flutter
        if (str_starts_with($module, 'package:')) {
            $withoutPrefix = mb_substr($module, 8); // Remove 'package:'
            $parts = explode('/', $withoutPrefix);

            return $parts[0];
        }

        // === Rust/Perl/R-specific handling ===
        // Rust: tokio::sync::mpsc -> tokio
        // Perl: Some::Module::Name -> Some
        // R: dplyr::filter -> dplyr
        if (str_contains($module, '::')) {
            $root = explode('::', $module)[0];

            return in_array($root, self::RUST_STD_MODULES, true) ? null : $root;
        }

        // === Clojure-specific handling ===
        // Clojure: clojure.core/map -> clojure.core -> clojure
        // But NOT Go URLs which contain slashes after dots (github.com/user/repo)
        if (str_contains($module, '/') && ! preg_match('/^[a-z]+\.[a-z]+\//', $module)) {
            // This is likely a Clojure qualified symbol
            $namespace = explode('/', $module)[0];

            // Extract root from namespace if it has dots
            if (str_contains($namespace, '.')) {
                return explode('.', $namespace)[0];
            }

            return $namespace;
        }

        // === Go-specific handling ===
        // Go: github.com/gin-gonic/gin -> keep full path (matches go.mod)
        // Identified by URL-like pattern
        if (preg_match('/^[a-z]+\.[a-z]+\//', $module)) {
            return $module;
        }

        // === Python/Java/C#/Scala/Kotlin/Elixir/Haskell/OCaml/Julia/Lua ===
        // All use dot notation: django.http -> django, com.example.Class -> com
        // Phoenix.Controller -> Phoenix, Data.List -> Data
        if (str_contains($module, '.')) {
            $root = explode('.', $module)[0];

            return in_array($root, self::STD_LIB_ROOTS, true) ? null : $root;
        }

        // === Simple names (Ruby, Swift, JS, etc.) ===
        // react, rails, Foundation, UIKit -> keep as-is
        return $module;
    }

    /**
     * Check if a dependency matches any of the imported modules.
     *
     * @param  array{name: string, version: string, dev?: bool}  $dependency
     * @param  array<string>  $importedModules
     */
    private function dependencyMatchesImport(array $dependency, array $importedModules): bool
    {
        $name = $dependency['name'];
        $nameLower = mb_strtolower($name);

        foreach ($importedModules as $module) {
            $moduleLower = mb_strtolower($module);

            // Exact match
            if ($nameLower === $moduleLower) {
                return true;
            }

            // Package name contains module (for things like "laravel/framework" matching "laravel/framework")
            if (str_contains($nameLower, $moduleLower)) {
                return true;
            }

            // Module name contains package (for things like "@angular/core" matching "angular")
            if (str_contains($moduleLower, $nameLower)) {
                return true;
            }

            // For composer packages, check if the package name (after /) matches
            // e.g., "laravel/framework" should match import "Illuminate" (handled by normalization)
            // But also "guzzlehttp/guzzle" should match import "GuzzleHttp"
            if (str_contains($name, '/')) {
                $parts = explode('/', $name);
                $packageName = end($parts);
                if (mb_strtolower($packageName) === $moduleLower) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Sort dependencies by usage - used dependencies first.
     *
     * @param  array<int, array{name: string, version: string, dev?: bool}>  $dependencies
     * @param  array<string>  $importedModules
     * @return array<int, array{name: string, version: string, dev?: bool}>
     */
    private function sortByUsage(array $dependencies, array $importedModules): array
    {
        $used = [];
        $unused = [];

        foreach ($dependencies as $dep) {
            if ($this->dependencyMatchesImport($dep, $importedModules)) {
                $used[] = $dep;
            } else {
                $unused[] = $dep;
            }
        }

        return array_merge($used, $unused);
    }
}
