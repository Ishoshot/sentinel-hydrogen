<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\SensitiveDataRedactor;

/**
 * Sanitizes project context data (languages, runtime, frameworks, dependencies) by redacting sensitive values.
 */
final readonly class ProjectContextSanitizer
{
    /**
     * Create a new ProjectContextSanitizer instance.
     */
    public function __construct(
        private SensitiveDataRedactor $redactor,
    ) {}

    /**
     * Sanitize all project context sections.
     *
     * @param  array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}  $projectContext
     * @return array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function sanitize(array $projectContext, int &$redactedCount): array
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
}
