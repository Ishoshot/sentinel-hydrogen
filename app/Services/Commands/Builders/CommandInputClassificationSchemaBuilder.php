<?php

declare(strict_types=1);

namespace App\Services\Commands\Builders;

use App\Enums\Commands\CommandInputDecision;
use App\Enums\Commands\CommandInputRiskLevel;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

final class CommandInputClassificationSchemaBuilder
{
    /**
     * @var array<int, string>
     */
    public const array RISK_TYPES = [
        'prompt_injection',
        'data_exfiltration',
        'secret_exposure',
        'privilege_escalation',
        'policy_bypass',
        'malicious_code_intent',
    ];

    /**
     * Build the structured schema for command input risk classification.
     */
    public function build(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'command_input_classification',
            description: 'Safety classification for a command query',
            properties: [
                new EnumSchema('verdict', 'Final decision', CommandInputDecision::values()),
                new EnumSchema('risk_level', 'Highest risk level', CommandInputRiskLevel::values()),
                new ArraySchema('risk_types', 'Detected risk categories', new EnumSchema(
                    'risk_type',
                    'Risk category',
                    self::RISK_TYPES,
                )),
                new NumberSchema('confidence', 'Classifier confidence between 0 and 1', maximum: 1, minimum: 0),
                new StringSchema('summary', 'Short classification reason'),
                new ArraySchema(
                    'signals',
                    'Key evidence signals',
                    new ObjectSchema(
                        name: 'signal',
                        description: 'A single classification signal',
                        properties: [
                            new EnumSchema('source', 'Signal origin', ['llm']),
                            new StringSchema('code', 'Signal code'),
                            new EnumSchema('severity', 'Signal severity', CommandInputRiskLevel::values()),
                            new StringSchema('evidence', 'Minimal evidence text'),
                        ],
                        requiredFields: ['source', 'code', 'severity', 'evidence'],
                    )
                ),
            ],
            requiredFields: ['verdict', 'risk_level', 'risk_types', 'confidence', 'summary', 'signals'],
        );
    }
}
