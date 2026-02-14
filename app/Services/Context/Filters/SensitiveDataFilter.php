<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use App\Services\Context\Filters\Support\SensitiveDataConversationSanitizer;
use App\Services\Context\Filters\Support\SensitiveDataSupplementalSanitizer;
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
        private SensitiveDataConversationSanitizer $conversationSanitizer,
        private SensitiveDataSupplementalSanitizer $supplementalSanitizer,
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

        $bag->pullRequest = $this->conversationSanitizer->sanitizePullRequest($bag->pullRequest, $redactedCount);
        $bag->linkedIssues = $this->conversationSanitizer->sanitizeLinkedIssues($bag->linkedIssues, $redactedCount);
        $bag->prComments = $this->conversationSanitizer->sanitizePrComments($bag->prComments, $redactedCount);

        $bag->fileContents = $this->supplementalSanitizer->sanitizeFileContents($bag->fileContents, $redactedCount);
        $bag->guidelines = $this->supplementalSanitizer->sanitizeGuidelines($bag->guidelines, $redactedCount);
        $bag->repositoryContext = $this->supplementalSanitizer->sanitizeRepositoryContext($bag->repositoryContext, $redactedCount);
        $bag->semantics = $this->supplementalSanitizer->sanitizeSemantics($bag->semantics, $redactedCount);
        $bag->projectContext = $this->supplementalSanitizer->sanitizeProjectContext($bag->projectContext, $redactedCount);

        if ($redactedCount > 0) {
            Log::info('SensitiveDataFilter: Redacted sensitive data', [
                'redacted_count' => $redactedCount,
            ]);
        }
    }
}
