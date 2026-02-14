<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Matches dependencies against imported modules and sorts by usage.
 */
final readonly class DependencyImportMatcher
{
    /**
     * Check if a dependency matches any of the imported modules.
     *
     * @param  array{name: string, version: string, dev?: bool}  $dependency
     * @param  array<string>  $importedModules
     */
    public function matches(array $dependency, array $importedModules): bool
    {
        $name = $dependency['name'];
        $nameLower = mb_strtolower($name);

        foreach ($importedModules as $module) {
            $moduleLower = mb_strtolower($module);

            // Exact match
            if ($nameLower === $moduleLower) {
                return true;
            }

            // Package name contains module
            if (str_contains($nameLower, $moduleLower)) {
                return true;
            }

            // Module name contains package
            if (str_contains($moduleLower, $nameLower)) {
                return true;
            }

            // For composer packages, check the package name (after /)
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
    public function sortByUsage(array $dependencies, array $importedModules): array
    {
        $used = [];
        $unused = [];

        foreach ($dependencies as $dep) {
            if ($this->matches($dep, $importedModules)) {
                $used[] = $dep;
            } else {
                $unused[] = $dep;
            }
        }

        return array_merge($used, $unused);
    }
}
