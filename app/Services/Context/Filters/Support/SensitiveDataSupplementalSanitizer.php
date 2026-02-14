<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\SensitiveDataRedactor;

final readonly class SensitiveDataSupplementalSanitizer
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private SensitiveDataRedactor $redactor,
    ) {}

    /**
     * @param  array<string, string>  $fileContents
     * @return array<string, string>
     */
    public function sanitizeFileContents(array $fileContents, int &$redactedCount): array
    {
        foreach ($fileContents as $key => $value) {
            $redacted = $this->redactor->redact($value);
            if ($redacted !== $value) {
                $redactedCount++;
            }

            $fileContents[$key] = $redacted;
        }

        return $fileContents;
    }

    /**
     * @param  array<int, array{path: string, description: string|null, content: string}>  $guidelines
     * @return array<int, array{path: string, description: string|null, content: string}>
     */
    public function sanitizeGuidelines(array $guidelines, int &$redactedCount): array
    {
        foreach ($guidelines as $index => $guideline) {
            $content = $this->redactor->redact($guideline['content']);
            if ($content !== $guideline['content']) {
                $redactedCount++;
            }

            $description = $guideline['description'];
            if ($description !== null) {
                $redactedDescription = $this->redactor->redact($description);
                if ($redactedDescription !== $description) {
                    $redactedCount++;
                }

                $description = $redactedDescription;
            }

            $guidelines[$index] = [
                'path' => $guideline['path'],
                'description' => $description,
                'content' => $content,
            ];
        }

        return $guidelines;
    }

    /**
     * @param  array{readme?: string|null, contributing?: string|null}  $repositoryContext
     * @return array{readme?: string|null, contributing?: string|null}
     */
    public function sanitizeRepositoryContext(array $repositoryContext, int &$redactedCount): array
    {
        foreach (['readme', 'contributing'] as $key) {
            if (! array_key_exists($key, $repositoryContext)) {
                continue;
            }

            if ($repositoryContext[$key] === null) {
                continue;
            }

            $value = $repositoryContext[$key];
            $redacted = $this->redactor->redact($value);
            if ($redacted !== $value) {
                $redactedCount++;
            }

            $repositoryContext[$key] = $redacted;
        }

        return $repositoryContext;
    }

    /**
     * @param  array<string, array<string, mixed>>  $semantics
     * @return array<string, array<string, mixed>>
     */
    public function sanitizeSemantics(array $semantics, int &$redactedCount): array
    {
        foreach ($semantics as $key => $semanticEntry) {
            /** @var array<string, mixed> $semanticEntry */
            $semantics[$key] = $this->sanitizeSemanticEntry($semanticEntry, $redactedCount);
        }

        return $semantics;
    }

    /**
     * @param  array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}  $projectContext
     * @return array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function sanitizeProjectContext(array $projectContext, int &$redactedCount): array
    {
        if (isset($projectContext['languages'])) {
            $projectContext['languages'] = array_map(function (string $language) use (&$redactedCount): string {
                $redacted = $this->redactor->redact($language);
                if ($redacted !== $language) {
                    $redactedCount++;
                }

                return $redacted;
            }, $projectContext['languages']);
        }

        if (isset($projectContext['runtime'])) {
            $runtime = $projectContext['runtime'];
            $name = $runtime['name'];
            $version = $runtime['version'];

            $redacted = $this->redactor->redact($name);
            if ($redacted !== $name) {
                $redactedCount++;
            }

            $runtime['name'] = $redacted;

            $redacted = $this->redactor->redact($version);
            if ($redacted !== $version) {
                $redactedCount++;
            }

            $runtime['version'] = $redacted;

            $projectContext['runtime'] = $runtime;
        }

        if (isset($projectContext['frameworks'])) {
            $projectContext['frameworks'] = array_map(function (array $framework) use (&$redactedCount): array {
                $name = $framework['name'];
                $version = $framework['version'];

                $redacted = $this->redactor->redact($name);
                if ($redacted !== $name) {
                    $redactedCount++;
                }

                $framework['name'] = $redacted;

                $redacted = $this->redactor->redact($version);
                if ($redacted !== $version) {
                    $redactedCount++;
                }

                $framework['version'] = $redacted;

                return $framework;
            }, $projectContext['frameworks']);
        }

        if (isset($projectContext['dependencies'])) {
            $projectContext['dependencies'] = array_map(function (array $dependency) use (&$redactedCount): array {
                $name = $dependency['name'];
                $version = $dependency['version'];

                $redacted = $this->redactor->redact($name);
                if ($redacted !== $name) {
                    $redactedCount++;
                }

                $dependency['name'] = $redacted;

                $redacted = $this->redactor->redact($version);
                if ($redacted !== $version) {
                    $redactedCount++;
                }

                $dependency['version'] = $redacted;

                return $dependency;
            }, $projectContext['dependencies']);
        }

        return $projectContext;
    }

    /**
     * @param  array<string, mixed>  $semanticEntry
     * @return array<string, mixed>
     */
    private function sanitizeSemanticEntry(array $semanticEntry, int &$redactedCount): array
    {
        foreach ($semanticEntry as $key => $value) {
            if (is_string($value)) {
                $redacted = $this->redactor->redact($value);
                if ($redacted !== $value) {
                    $redactedCount++;
                }

                $semanticEntry[$key] = $redacted;

                continue;
            }

            if (is_array($value)) {
                $semanticEntry[$key] = $this->sanitizeNestedArray($value, $redactedCount);
            }
        }

        return $semanticEntry;
    }

    /**
     * @param  array<mixed, mixed>  $nested
     * @return array<mixed, mixed>
     */
    private function sanitizeNestedArray(array $nested, int &$redactedCount): array
    {
        foreach ($nested as $key => $value) {
            if (is_string($value)) {
                $redacted = $this->redactor->redact($value);
                if ($redacted !== $value) {
                    $redactedCount++;
                }

                $nested[$key] = $redacted;

                continue;
            }

            if (is_array($value)) {
                $nested[$key] = $this->sanitizeNestedArray($value, $redactedCount);
            }
        }

        return $nested;
    }
}
