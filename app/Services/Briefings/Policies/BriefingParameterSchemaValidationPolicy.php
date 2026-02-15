<?php

declare(strict_types=1);

namespace App\Services\Briefings\Policies;

use App\Services\Briefings\Builders\BriefingParameterSchemaMessageBuilder;
use App\Services\Briefings\Builders\BriefingParameterSchemaRuleBuilder;
use RuntimeException;

final readonly class BriefingParameterSchemaValidationPolicy
{
    /**
     * Create a new schema validator instance.
     */
    public function __construct(
        private BriefingParameterSchemaRuleBuilder $ruleBuilder = new BriefingParameterSchemaRuleBuilder,
        private BriefingParameterSchemaMessageBuilder $messageBuilder = new BriefingParameterSchemaMessageBuilder,
    ) {}

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
        $properties = $this->resolveProperties($schema);
        $required = $this->resolveRequired($schema);

        foreach ($properties as $field => $definition) {
            if (! is_string($field)) {
                throw new RuntimeException('Briefing parameter schema property names must be strings.');
            }

            if (! is_array($definition)) {
                throw new RuntimeException(sprintf('Invalid schema definition for "%s".', $field));
            }

            /** @var array<string, mixed> $typedDefinition */
            $typedDefinition = $definition;

            $rules[$field] = $this->ruleBuilder->build($field, $typedDefinition, $required);
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
        $properties = $this->resolveProperties($schema);

        foreach ($properties as $field => $definition) {
            if (! is_string($field)) {
                continue;
            }

            if (! is_array($definition)) {
                continue;
            }

            /** @var array<string, mixed> $typedDefinition */
            $typedDefinition = $definition;

            $messages = array_merge($messages, $this->messageBuilder->build($field, $typedDefinition));
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<mixed, mixed>
     */
    private function resolveProperties(array $schema): array
    {
        $properties = $schema['properties'] ?? null;

        if (! is_array($properties) || $properties === []) {
            throw new RuntimeException('Briefing parameter schema must define properties.');
        }

        return $properties;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function resolveRequired(array $schema): array
    {
        /** @var array<int, string> $required */
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        return $required;
    }
}
