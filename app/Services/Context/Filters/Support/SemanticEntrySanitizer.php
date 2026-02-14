<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\SensitiveDataRedactor;

/**
 * Recursively sanitizes semantic analysis entries by redacting sensitive data.
 */
final readonly class SemanticEntrySanitizer
{
    public function __construct(
        private SensitiveDataRedactor $redactor,
    ) {}

    /**
     * Sanitize all semantic entries.
     *
     * @param  array<string, array<string, mixed>>  $semantics
     * @return array<string, array<string, mixed>>
     */
    public function sanitize(array $semantics, int &$redactedCount): array
    {
        foreach ($semantics as $key => $semanticEntry) {
            /** @var array<string, mixed> $semanticEntry */
            $semantics[$key] = $this->sanitizeEntry($semanticEntry, $redactedCount);
        }

        return $semantics;
    }

    /**
     * @param  array<string, mixed>  $semanticEntry
     * @return array<string, mixed>
     */
    private function sanitizeEntry(array $semanticEntry, int &$redactedCount): array
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
