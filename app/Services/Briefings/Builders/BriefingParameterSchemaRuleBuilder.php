<?php

declare(strict_types=1);

namespace App\Services\Briefings\Support;

use RuntimeException;

final class BriefingParameterSchemaRuleBuilder
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, string>  $required
     * @return array<int, string>
     */
    public function build(string $field, array $definition, array $required): array
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

    /**
     * ResolveTypeRule.
     */
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

    /**
     * ResolveFormatRule.
     */
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
