<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use App\Services\Context\SensitiveDataRedactor;
use Illuminate\Support\Facades\Log;

/**
 * Removes or redacts sensitive data from context.
 *
 * Identifies and redacts potential secrets, API keys, passwords, and other
 * sensitive information to prevent exposure to the LLM.
 */
final readonly class SensitiveDataFilter implements ContextFilter
{
    /**
     * Create a new filter instance.
     */
    public function __construct(
        private SensitiveDataRedactor $redactor,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'sensitive_data';
    }

    /**
     * {@inheritdoc}
     */
    public function order(): int
    {
        return 30; // Run early, after path filters but before token limits
    }

    /**
     * {@inheritdoc}
     */
    public function filter(ContextBag $bag): void
    {
        $redactedCount = 0;

        // Filter file patches
        $bag->files = array_map(function (array $file) use (&$redactedCount): array {
            $filename = $file['filename'];

            // Completely redact sensitive files
            if ($this->redactor->isSensitiveFile($filename)) {
                if ($file['patch'] !== null) {
                    $file['patch'] = '[REDACTED - sensitive file]';
                    $redactedCount++;
                }

                return $file;
            }

            // Redact sensitive patterns in patches
            if ($file['patch'] !== null) {
                $original = $file['patch'];
                $file['patch'] = $this->redactor->redact($file['patch']);

                if ($original !== $file['patch']) {
                    $redactedCount++;
                }
            }

            return $file;
        }, $bag->files);

        // Filter PR body in pullRequest data
        if (isset($bag->pullRequest['body']) && is_string($bag->pullRequest['body'])) {
            $original = $bag->pullRequest['body'];
            $bag->pullRequest['body'] = $this->redactor->redact($bag->pullRequest['body']);

            if ($original !== $bag->pullRequest['body']) {
                $redactedCount++;
            }
        }

        // Filter linked issue bodies and comments
        $bag->linkedIssues = array_map(function (array $issue) use (&$redactedCount): array {
            if ($issue['body'] !== null) {
                $original = $issue['body'];
                $issue['body'] = $this->redactor->redact($issue['body']);

                if ($original !== $issue['body']) {
                    $redactedCount++;
                }
            }

            $issue['comments'] = array_map(function (array $comment) use (&$redactedCount): array {
                $original = $comment['body'];
                $comment['body'] = $this->redactor->redact($comment['body']);

                if ($original !== $comment['body']) {
                    $redactedCount++;
                }

                return $comment;
            }, $issue['comments']);

            return $issue;
        }, $bag->linkedIssues);

        // Filter PR comments
        $bag->prComments = array_map(function (array $comment) use (&$redactedCount): array {
            $original = $comment['body'];
            $comment['body'] = $this->redactor->redact($comment['body']);

            if ($original !== $comment['body']) {
                $redactedCount++;
            }

            return $comment;
        }, $bag->prComments);

        // Filter full file contents
        $bag->fileContents = $this->sanitizeFileContents($bag->fileContents, $redactedCount);

        // Filter repository guidelines (content + description)
        $bag->guidelines = $this->sanitizeGuidelines($bag->guidelines, $redactedCount);

        // Filter repository context docs (README / CONTRIBUTING)
        $bag->repositoryContext = $this->sanitizeRepositoryContext($bag->repositoryContext, $redactedCount);

        // Filter semantic analysis data
        $bag->semantics = $this->sanitizeSemantics($bag->semantics, $redactedCount);

        // Filter project context
        $bag->projectContext = $this->sanitizeProjectContext($bag->projectContext, $redactedCount);

        if ($redactedCount > 0) {
            Log::info('SensitiveDataFilter: Redacted sensitive data', [
                'redacted_count' => $redactedCount,
            ]);
        }
    }

    /**
     * Sanitize full file contents.
     *
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private function sanitizeFileContents(array $data, int &$redactedCount): array
    {
        foreach ($data as $key => $value) {
            $redacted = $this->redactor->redact($value);
            if ($redacted !== $value) {
                $redactedCount++;
            }

            $data[$key] = $redacted;
        }

        return $data;
    }

    /**
     * Sanitize guideline content and descriptions.
     *
     * @param  array<int, array{path: string, description: string|null, content: string}>  $data
     * @return array<int, array{path: string, description: string|null, content: string}>
     */
    private function sanitizeGuidelines(array $data, int &$redactedCount): array
    {
        foreach ($data as $index => $guideline) {
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

            $data[$index] = [
                'path' => $guideline['path'],
                'description' => $description,
                'content' => $content,
            ];
        }

        return $data;
    }

    /**
     * Sanitize repository context docs.
     *
     * @param  array{readme?: string|null, contributing?: string|null}  $data
     * @return array{readme?: string|null, contributing?: string|null}
     */
    private function sanitizeRepositoryContext(array $data, int &$redactedCount): array
    {
        foreach (['readme', 'contributing'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            if ($data[$key] === null) {
                continue;
            }

            $value = $data[$key];
            $redacted = $this->redactor->redact($value);
            if ($redacted !== $value) {
                $redactedCount++;
            }

            $data[$key] = $redacted;
        }

        return $data;
    }

    /**
     * Sanitize semantic analysis data.
     *
     * @param  array<string, array<string, mixed>>  $data
     * @return array<string, array<string, mixed>>
     */
    private function sanitizeSemantics(array $data, int &$redactedCount): array
    {
        foreach ($data as $key => $value) {
            /** @var array<string, mixed> $value */
            $data[$key] = $this->sanitizeSemanticEntry($value, $redactedCount);
        }

        return $data;
    }

    /**
     * Sanitize project context data.
     *
     * @param  array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}  $data
     * @return array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function sanitizeProjectContext(array $data, int &$redactedCount): array
    {
        if (isset($data['languages'])) {
            $data['languages'] = array_map(function (string $language) use (&$redactedCount): string {
                $redacted = $this->redactor->redact($language);
                if ($redacted !== $language) {
                    $redactedCount++;
                }

                return $redacted;
            }, $data['languages']);
        }

        if (isset($data['runtime'])) {
            $runtime = $data['runtime'];
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

            $data['runtime'] = $runtime;
        }

        if (isset($data['frameworks'])) {
            $data['frameworks'] = array_map(function (array $framework) use (&$redactedCount): array {
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
            }, $data['frameworks']);
        }

        if (isset($data['dependencies'])) {
            $data['dependencies'] = array_map(function (array $dependency) use (&$redactedCount): array {
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
            }, $data['dependencies']);
        }

        return $data;
    }

    /**
     * Sanitize a semantic analysis entry.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeSemanticEntry(array $data, int &$redactedCount): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $redacted = $this->redactor->redact($value);
                if ($redacted !== $value) {
                    $redactedCount++;
                }

                $data[$key] = $redacted;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeNestedArray($value, $redactedCount);
            }
        }

        return $data;
    }

    /**
     * Recursively sanitize arrays by redacting sensitive data in strings.
     *
     * @param  array<mixed, mixed>  $data
     * @return array<mixed, mixed>
     */
    private function sanitizeNestedArray(array $data, int &$redactedCount): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $redacted = $this->redactor->redact($value);
                if ($redacted !== $value) {
                    $redactedCount++;
                }

                $data[$key] = $redacted;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeNestedArray($value, $redactedCount);
            }
        }

        return $data;
    }
}
