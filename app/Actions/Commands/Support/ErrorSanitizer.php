<?php

declare(strict_types=1);

namespace App\Actions\Commands\Support;

/**
 * Sanitizes error messages by redacting secrets, escaping markdown, and truncating.
 */
final readonly class ErrorSanitizer
{
    private const int MAX_LENGTH = 200;

    /**
     * Sanitize an error message for safe display in a GitHub comment.
     */
    public function sanitize(string $message): string
    {
        // Remove API keys, tokens, etc.
        $message = preg_replace('/sk-[a-zA-Z0-9]{20,}/', '[REDACTED]', $message) ?? $message;
        $message = preg_replace('/Bearer [a-zA-Z0-9\-_.]+/', 'Bearer [REDACTED]', $message) ?? $message;

        // Escape markdown special characters to prevent injection
        $message = str_replace(
            ['[', ']', '(', ')', '*', '_', '`', '<', '>'],
            ['\\[', '\\]', '\\(', '\\)', '\\*', '\\_', '\\`', '&lt;', '&gt;'],
            $message
        );

        // Truncate long messages
        if (mb_strlen($message) > self::MAX_LENGTH) {
            return mb_substr($message, 0, self::MAX_LENGTH).'...';
        }

        return $message;
    }
}
