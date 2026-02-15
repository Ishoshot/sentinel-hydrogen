<?php

declare(strict_types=1);

namespace App\Services\Commands\Parsers;

use App\Models\CommandRun;
use App\Services\Commands\Builders\FooterMetricsBuilder;
use App\Services\Commands\Strategies\ErrorSanitizationStrategy;
use Throwable;

/**
 * Parses/formats success and error response bodies as GitHub-flavored markdown.
 */
final readonly class ResponseMarkdownParser
{
    private const int MAX_RESPONSE_LENGTH = 60000;

    /**
     * Create a new ResponseMarkdownParser instance.
     */
    public function __construct(
        private FooterMetricsBuilder $footerBuilder,
        private ErrorSanitizationStrategy $errorSanitizationStrategy,
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
        $errorMessage = $this->errorSanitizationStrategy->sanitize($exception->getMessage());

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
