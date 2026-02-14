<?php

declare(strict_types=1);

namespace App\Services\Briefings\Support;

use RuntimeException;

final class BriefingParameterSchemaValidator
{
    /**
     * Build Laravel validation rules from a briefing parameter schema.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, array<int, string>>
     */
    public function rules(array $schema): array
    {
        /** @var array<string, array<int, string>> $rules */
        $rules = [];
        $properties = $this->normalizedProperties($schema);

        /** @var array<int, string> $required */
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        foreach ($properties as $field => $definition) {
            if (! is_string($field)) {
                throw new RuntimeException('Briefing parameter schema property names must be strings.');
            }

            if (! is_array($definition)) {
                throw new RuntimeException(sprintf('Invalid schema definition for "%s".', $field));
            }

            /** @var array<string, mixed> $typedDefinition */
            $typedDefinition = $definition;

            $rules[$field] = $this->rulesForField($field, $typedDefinition, $required);
        }

        return $rules;
    }

    /**
     * Build validation messages from a briefing parameter schema.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, string>
     */
    public function messages(array $schema): array
    {
        $messages = [];
        $properties = $this->normalizedProperties($schema);

        foreach ($properties as $field => $definition) {
            if (! is_string($field) || ! is_array($definition)) {
                continue;
            }

            if (isset($definition['description'])) {
                $messages[$field.'.required'] = (string) $definition['description'];
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<mixed, mixed>
     */
    private function normalizedProperties(array $schema): array
    {
        $properties = $schema['properties'] ?? null;

        if (! is_array($properties) || $properties === []) {
            throw new RuntimeException('Briefing parameter schema must define properties.');
        }

        return $properties;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, string>  $required
     * @return array<int, string>
     */
    private function rulesForField(string $field, array $definition, array $required): array
    {
        $fieldRules = [];
        $fieldRules[] = in_array($field, $required, true) ? 'required' : 'nullable';

        $fieldType = $definition['type'] ?? null;
        $fieldRules[] = $this->resolveTypeRule($field, $fieldType);

        if (isset($definition['format'])) {
            $formatRule = $this->resolveFormatRule($definition['format']);

            if ($formatRule !== null) {
                $fieldRules[] = $formatRule;
            }
        }

        if (isset($definition['minimum'])) {
            $fieldRules[] = 'min:'.(int) $definition['minimum'];
        }

        if (isset($definition['maximum'])) {
            $fieldRules[] = 'max:'.(int) $definition['maximum'];
        }

        if (isset($definition['minLength'])) {
            $fieldRules[] = 'min:'.(int) $definition['minLength'];
        }

        if (isset($definition['maxLength'])) {
            $fieldRules[] = 'max:'.(int) $definition['maxLength'];
        }

        if (isset($definition['enum']) && is_array($definition['enum'])) {
            /** @var array<int, string> $enumValues */
            $enumValues = $definition['enum'];
            $fieldRules[] = 'in:'.implode(',', $enumValues);
        }

        if ($fieldType === 'array' && isset($definition['items'])) {
            if (isset($definition['minItems'])) {
                $fieldRules[] = 'min:'.(int) $definition['minItems'];
            }

            if (isset($definition['maxItems'])) {
                $fieldRules[] = 'max:'.(int) $definition['maxItems'];
            }
        }

        return $fieldRules;
    }

    private function resolveTypeRule(string $field, mixed $fieldType): string
    {
        if (! is_string($fieldType)) {
            throw new RuntimeException(sprintf('Missing or invalid type for "%s".', $field));
        }

        return match ($fieldType) {
            'string' => 'string',
            'integer' => 'integer',
            'number' => 'numeric',
            'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'array',
            default => throw new RuntimeException(sprintf('Unsupported type "%s" for "%s".', $fieldType, $field)),
        };
    }

    private function resolveFormatRule(mixed $format): ?string
    {
        return match ((string) $format) {
            'date', 'date-time' => 'date',
            'email' => 'email',
            'uri', 'url' => 'url',
            default => null,
        };
    }
}
