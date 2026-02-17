<?php

declare(strict_types=1);

namespace App\Services\Reviews\Builders;

use App\Enums\Reviews\FindingCategory;
use App\Enums\Reviews\ReviewVerdict;
use App\Enums\Reviews\RiskLevel;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * Builds the Prism structured schema for review responses.
 */
final class PrismReviewSchemaBuilder
{
    /**
     * Build the structured schema used for AI review responses.
     */
    public function build(): ObjectSchema
    {
        $summarySchema = new ObjectSchema(
            name: 'summary',
            description: 'Overall review summary',
            properties: [
                new StringSchema('overview', 'Comprehensive overview starting with methodology checklist'),
                new EnumSchema('verdict', 'Review verdict', ReviewVerdict::values()),
                new EnumSchema('risk_level', 'Overall risk level', RiskLevel::values()),
                new ArraySchema('strengths', 'List of positive aspects', new StringSchema('strength', 'A strength')),
                new ArraySchema('concerns', 'List of concerns', new StringSchema('concern', 'A concern')),
                new ArraySchema('recommendations', 'List of recommendations', new StringSchema('recommendation', 'A recommendation')),
            ],
            requiredFields: ['overview', 'verdict', 'risk_level'],
        );

        $findingSchema = new ObjectSchema(
            name: 'finding',
            description: 'A single code review finding',
            properties: [
                new EnumSchema('severity', 'Severity level', SentinelConfigSeverity::values()),
                new EnumSchema('category', 'Finding category', FindingCategory::values()),
                new StringSchema('title', 'Short title of the finding'),
                new StringSchema('description', 'Detailed description of the issue'),
                new NumberSchema('confidence', 'Confidence score between 0 and 1'),
                new StringSchema('impact', 'Why this matters and its potential impact'),
                new StringSchema('file_path', 'Path to the file'),
                new NumberSchema('line_start', 'Starting line number'),
                new NumberSchema('line_end', 'Ending line number'),
                new StringSchema('current_code', 'The current code exactly as it appears in the file, preserving all leading indentation (spaces/tabs)'),
                new StringSchema('replacement_code', 'The suggested replacement code, preserving the exact same leading indentation (spaces/tabs) as the current code'),
                new StringSchema('explanation', 'Explanation of the code change'),
                new ArraySchema('references', 'Sources: markdown links [Text](url), repo guidelines, or plain text standards', new StringSchema('reference', 'A reference source')),
            ],
            requiredFields: ['severity', 'category', 'title', 'description', 'confidence'],
        );

        return new ObjectSchema(
            name: 'review_response',
            description: 'Complete code review response',
            properties: [
                $summarySchema,
                new ArraySchema('findings', 'List of findings', $findingSchema),
            ],
            requiredFields: ['summary', 'findings'],
        );
    }
}
