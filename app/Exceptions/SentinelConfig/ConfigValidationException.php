<?php

declare(strict_types=1);

namespace App\Exceptions\SentinelConfig;

use Exception;

/**
 * Exception thrown when schema validation fails.
 */
final class ConfigValidationException extends Exception
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = []
    ) {
        parent::__construct($message);
    }

    /**
     * Create exception from Laravel validation errors.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    public static function fromValidationErrors(array $errors): self
    {
        $messages = [];

        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = sprintf('%s: %s', $field, $error);
            }
        }

        return new self(
            'Configuration validation failed: '.implode('; ', array_slice($messages, 0, 5)),
            $errors
        );
    }

    /**
     * Get the validation errors.
     *
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
