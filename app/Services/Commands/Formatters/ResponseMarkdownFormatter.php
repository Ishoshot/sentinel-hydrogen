<?php

declare(strict_types=1);

namespace App\Services\Commands\Formatters;

use App\Models\CommandRun;
use App\Services\Commands\Strategies\ErrorSanitizationStrategy;
use Throwable;

/**
 * Parses/formats success and error response bodies as GitHub-flavored markdown.
 */
final readonly class ResponseMarkdownFormatter
{
    private const int MAX_RESPONSE_LENGTH = 60000;

    private const string POWERED_BY = 'Powered by [Sentinel](https://sentinelapp.dev)';

    /**
     * Create a new ResponseMarkdownFormatter instance.
     */
    public function __construct(
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
        $footer = $this->buildFooter($metrics);

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

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function buildFooter(array $metrics): string
    {
        $parts = [];

        if (isset($metrics['model'])) {
            $parts[] = sprintf('Model: `%s`', $metrics['model']);
        }

        if (isset($metrics['duration_ms']) && is_numeric($metrics['duration_ms'])) {
            $duration = number_format((float) $metrics['duration_ms'] / 1000, 1);
            $parts[] = sprintf('Time: %ss', $duration);
        }

        if ($parts === []) {
            return "\n\n---\n*".self::POWERED_BY.'*';
        }

        return "\n\n---\n<sub>".implode(' | ', $parts).' | '.self::POWERED_BY.'</sub>';
    }
}
