<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Models\Briefing;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class BriefingParameterValidator
{
    /**
     * Validate parameters against a briefing's parameter schema.
     *
     * @param  Briefing  $briefing  The briefing template
     * @param  array<string, mixed>  $parameters  The parameters to validate
     * @return BriefingParameters The validated parameters
     *
     * @throws ValidationException
     */
    public function validate(Briefing $briefing, array $parameters): BriefingParameters
    {
        $schema = $briefing->parameter_schema ?? [];

        if (empty($schema)) {
            return BriefingParameters::fromArray($parameters);
        }

        $rules = $this->buildValidationRules($schema);
        $messages = $this->buildValidationMessages($schema);

        $validator = Validator::make($parameters, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        $this->enforceConfigLimits($validated);

        return BriefingParameters::fromArray($validated);
    }

    /**
     * Enforce global briefing limits from configuration.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws ValidationException
     */
    private function enforceConfigLimits(array $parameters): void
    {
        $this->enforceRepositoryLimit($parameters);
        $this->enforceDateRangeLimit($parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws ValidationException
     */
    private function enforceRepositoryLimit(array $parameters): void
    {
        $maxRepositories = (int) config('briefings.limits.max_repositories', 10);

        if ($maxRepositories <= 0) {
            return;
        }

        $repositoryIds = $parameters['repository_ids'] ?? null;

        if (! is_array($repositoryIds)) {
            return;
        }

        if (count($repositoryIds) > $maxRepositories) {
            throw ValidationException::withMessages([
                'repository_ids' => sprintf('You can select up to %d repositories for a briefing.', $maxRepositories),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws ValidationException
     */
    private function enforceDateRangeLimit(array $parameters): void
    {
        $maxDays = (int) config('briefings.limits.max_date_range_days', 90);

        if ($maxDays <= 0) {
            return;
        }

        $end = isset($parameters['end_date'])
            ? Carbon::parse((string) $parameters['end_date'])
            : now();

        $start = isset($parameters['start_date'])
            ? Carbon::parse((string) $parameters['start_date'])
            : $end->copy()->subDays(7);

        if ($start->greaterThan($end)) {
            throw ValidationException::withMessages([
                'start_date' => 'Start date must be before end date.',
            ]);
        }

        $rangeDays = $start->diffInDays($end) + 1;

        if ($rangeDays > $maxDays) {
            throw ValidationException::withMessages([
                'end_date' => sprintf('Date range cannot exceed %d days.', $maxDays),
            ]);
        }
    }

    /**
     * Build Laravel validation rules from JSON schema.
     *
     * @param  array<string, mixed>  $schema  The JSON schema
     * @return array<string, array<int, string>> The validation rules
     */
    private function buildValidationRules(array $schema): array
    {
        /** @var array<string, array<int, string>> $rules */
        $rules = [];

        $properties = $schema['properties'] ?? null;

        if (! is_array($properties) || $properties === []) {
            throw new RuntimeException('Briefing parameter schema must define properties.');
        }

        /** @var array<int, string> $required */
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        foreach ($properties as $field => $definition) {
            if (! is_string($field)) {
                throw new RuntimeException('Briefing parameter schema property names must be strings.');
            }

            if (! is_array($definition)) {
                throw new RuntimeException(sprintf('Invalid schema definition for "%s".', $field));
            }

            $fieldRules = [];

            // Required check
            $fieldRules[] = in_array($field, $required, true) ? 'required' : 'nullable';

            // Type rules
            $type = $definition['type'] ?? null;

            if (! is_string($type)) {
                throw new RuntimeException(sprintf('Missing or invalid type for "%s".', $field));
            }

            $fieldRules[] = match ($type) {
                'string' => 'string',
                'integer' => 'integer',
                'number' => 'numeric',
                'boolean' => 'boolean',
                'array' => 'array',
                'object' => 'array',
                default => throw new RuntimeException(sprintf('Unsupported type "%s" for "%s".', $type, $field)),
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
