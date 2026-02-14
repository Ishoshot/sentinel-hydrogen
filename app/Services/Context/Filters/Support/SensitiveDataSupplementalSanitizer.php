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
        private SemanticEntrySanitizer $semanticEntrySanitizer,
        private ProjectContextSanitizer $projectContextSanitizer,
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
        return $this->semanticEntrySanitizer->sanitize($semantics, $redactedCount);
    }

    /**
     * @param  array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}  $projectContext
     * @return array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function sanitizeProjectContext(array $projectContext, int &$redactedCount): array
    {
        return $this->projectContextSanitizer->sanitize($projectContext, $redactedCount);
    }
}
