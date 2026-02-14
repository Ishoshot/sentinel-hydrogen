<?php

declare(strict_types=1);

namespace App\Actions\Commands\Support;

use App\Models\CommandRun;
use Throwable;

/**
 * Formats success and error response bodies as GitHub-flavored markdown.
 */
final readonly class ResponseMarkdownFormatter
{
    private const int MAX_RESPONSE_LENGTH = 60000;

    /**
     * Create a new ResponseMarkdownFormatter instance.
     */
    public function __construct(
        private FooterMetricsBuilder $footerBuilder,
        private ErrorSanitizer $errorSanitizer,
    ) {}

    /**
     * Format a successful response body for GitHub.
     */
    public function formatSuccess(CommandRun $commandRun, string $answer): string
    {
        $commandType = $commandRun->command_type->description();
        $metrics = $commandRun->metrics ?? [];

        if (mb_strlen($answer) > self::MAX_RESPONSE_LENGTH) {
            $answer = mb_substr($answer, 0, self::MAX_RESPONSE_LENGTH)
                ."\n\n---\n*Response truncated due to length.*";
        }

        $header = "### Sentinel - {$commandType}\n\n";
        $footer = $this->footerBuilder->build($metrics);

        return $header.$answer.$footer;
    }

    /**
     * Format an error response body for GitHub.
     */
    public function formatError(CommandRun $commandRun, Throwable $exception): string
    {
        $commandType = $commandRun->command_type->description();
        $errorMessage = $this->errorSanitizer->sanitize($exception->getMessage());

        return <<<MD
### Sentinel - {$commandType}

I encountered an error while processing your request:

> {$errorMessage}

Please try again later. If the problem persists, contact support.

---
*Powered by [Sentinel](https://sentinelapp.dev)*
MD;
    }
}
