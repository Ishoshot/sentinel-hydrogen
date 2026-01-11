<?php

declare(strict_types=1);

namespace App\Exceptions\SentinelConfig;

use Exception;

/**
 * Exception thrown when YAML parsing fails.
 */
final class ConfigParseException extends Exception
{
    /**
     * Create exception for empty content.
     */
    public static function emptyContent(): self
    {
        return new self('Configuration file is empty');
    }

    /**
     * Create exception for YAML syntax errors.
     */
    public static function syntaxError(string $message, ?int $line = null): self
    {
        $prefix = $line !== null ? sprintf('Line %d: ', $line) : '';

        return new self(sprintf('YAML syntax error: %s%s', $prefix, $message));
    }

    /**
     * Create exception for invalid structure.
     */
    public static function invalidStructure(string $message): self
    {
        return new self('Invalid configuration structure: '.$message);
    }
}
