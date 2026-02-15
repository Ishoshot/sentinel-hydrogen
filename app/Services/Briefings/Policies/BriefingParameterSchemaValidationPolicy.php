<?php

declare(strict_types=1);

namespace App\Services\Briefings\Policies;

use App\Services\Briefings\Builders\BriefingParameterSchemaMessageBuilder;
use App\Services\Briefings\Builders\BriefingParameterSchemaRuleBuilder;
use App\Services\Briefings\Strategies\BriefingParameterSchemaPropertyStrategy;
use RuntimeException;

final readonly class BriefingParameterSchemaValidationPolicy
{
    /**
     * Create a new schema validator instance.
     */
    public function __construct(
        private BriefingParameterSchemaPropertyStrategy $propertyResolver = new BriefingParameterSchemaPropertyStrategy,
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
        $properties = $this->propertyResolver->resolve($schema);
        $required = $this->propertyResolver->required($schema);

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
        $properties = $this->propertyResolver->resolve($schema);

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
}
