<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Prioritizes and limits dependencies based on semantic import usage.
 */
final readonly class ProjectDependencyPrioritizer
{
    private const int MAX_MAIN_DEPENDENCIES = 50;

    private const int MAX_DEV_DEPENDENCIES = 20;

    /**
     * Normalizes import/module identifiers across language ecosystems.
     */
    private ModuleNameNormalizer $moduleNormalizer;

    /**
     * Matches dependencies against imported modules and prioritizes used entries.
     */
    private DependencyImportMatcher $importMatcher;

    /**
     * Create a new ProjectDependencyPrioritizer instance.
     */
    public function __construct()
    {
        $this->moduleNormalizer = new ModuleNameNormalizer;
        $this->importMatcher = new DependencyImportMatcher;
    }

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
                    $normalized = $this->moduleNormalizer->normalize($import['module']);

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
            $main = $this->importMatcher->sortByUsage($main, $importedModules);
            $dev = $this->importMatcher->sortByUsage($dev, $importedModules);
        }

        $main = array_slice($main, 0, self::MAX_MAIN_DEPENDENCIES);
        $dev = array_slice($dev, 0, self::MAX_DEV_DEPENDENCIES);

        return array_merge($main, $dev);
    }
}
