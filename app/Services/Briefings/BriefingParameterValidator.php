<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Models\Briefing;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class BriefingParameterValidator
{
    /**
     * Validate parameters against a briefing's parameter schema.
     *
     * @param  Briefing  $briefing  The briefing template
     * @param  array<string, mixed>  $parameters  The parameters to validate
     * @return array<string, mixed> The validated parameters
     *
     * @throws ValidationException
     */
    public function validate(Briefing $briefing, array $parameters): array
    {
        $schema = $briefing->parameter_schema ?? [];

        if (empty($schema)) {
            return $parameters;
        }

        $rules = $this->buildValidationRules($schema);
        $messages = $this->buildValidationMessages($schema);

        $validator = Validator::make($parameters, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        return $validated;
    }

    /**
     * Build Laravel validation rules from JSON schema.
     *
     * @param  array<string, mixed>  $schema  The JSON schema
     * @return array<string, array<int, string>> The validation rules
     */
    private function buildValidationRules(array $schema): array
    {
        $rules = [];

        /** @var array<string, array<string, mixed>> $properties */
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        /** @var array<int, string> $required */
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        foreach ($properties as $field => $definition) {
            $fieldRules = [];

            // Required check
            $fieldRules[] = in_array($field, $required, true) ? 'required' : 'nullable';

            // Type rules
            $type = (string) ($definition['type'] ?? 'string');
            $fieldRules[] = match ($type) {
                'string' => 'string',
                'integer' => 'integer',
                'number' => 'numeric',
                'boolean' => 'boolean',
                'array' => 'array',
                'object' => 'array',
                default => 'string',
            };

            // Format rules
            if (isset($definition['format'])) {
                $format = (string) $definition['format'];
                $formatRule = match ($format) {
                    'date' => 'date',
                    'date-time' => 'date',
                    'email' => 'email',
                    'uri', 'url' => 'url',
                    default => '',
                };
                if ($formatRule !== '') {
                    $fieldRules[] = $formatRule;
                }
            }

            // Min/max rules
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

            // Enum rules
            if (isset($definition['enum']) && is_array($definition['enum'])) {
                /** @var array<int, string> $enumValues */
                $enumValues = $definition['enum'];
                $fieldRules[] = 'in:'.implode(',', $enumValues);
            }

            // Array items rules
            if ($type === 'array' && isset($definition['items'])) {
                if (isset($definition['minItems'])) {
                    $fieldRules[] = 'min:'.(int) $definition['minItems'];
                }

                if (isset($definition['maxItems'])) {
                    $fieldRules[] = 'max:'.(int) $definition['maxItems'];
                }
            }

            $rules[$field] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Build validation messages from JSON schema.
     *
     * @param  array<string, mixed>  $schema  The JSON schema
     * @return array<string, string> The validation messages
     */
    private function buildValidationMessages(array $schema): array
    {
        $messages = [];

        /** @var array<string, array<string, mixed>> $properties */
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        foreach ($properties as $field => $definition) {
            if (isset($definition['description'])) {
                $messages[$field.'.required'] = (string) $definition['description'];
            }
        }

        return $messages;
    }
}
