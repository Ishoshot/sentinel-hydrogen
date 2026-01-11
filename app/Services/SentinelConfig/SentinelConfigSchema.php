<?php

declare(strict_types=1);

namespace App\Services\SentinelConfig;

use App\Enums\AiProvider;
use App\Enums\AnnotationStyle;
use App\Enums\SentinelConfigSeverity;
use App\Enums\SentinelConfigTone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Defines and validates the schema for .sentinel/config.yaml files.
 */
final class SentinelConfigSchema
{
    public const int MIN_VERSION = 1;

    public const int MAX_VERSION = 1;

    public const int DEFAULT_MAX_FINDINGS = 25;

    public const int MAX_MAX_FINDINGS = 100;

    public const int MAX_GUIDELINES = 10;

    public const int MAX_FOCUS_ITEMS = 20;

    public const int MAX_PATTERN_LENGTH = 256;

    /**
     * Validate configuration data against the schema.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $validator = Validator::make($data, $this->rules(), $this->messages());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Get validation rules for the configuration schema.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Version
            'version' => ['required', 'integer', 'min:'.self::MIN_VERSION, 'max:'.self::MAX_VERSION],

            // Triggers section
            'triggers' => ['sometimes', 'array'],
            'triggers.target_branches' => ['sometimes', 'array', 'max:50'],
            'triggers.target_branches.*' => ['string', 'max:'.self::MAX_PATTERN_LENGTH],
            'triggers.skip_source_branches' => ['sometimes', 'array', 'max:50'],
            'triggers.skip_source_branches.*' => ['string', 'max:'.self::MAX_PATTERN_LENGTH],
            'triggers.skip_labels' => ['sometimes', 'array', 'max:50'],
            'triggers.skip_labels.*' => ['string', 'max:100'],
            'triggers.skip_authors' => ['sometimes', 'array', 'max:50'],
            'triggers.skip_authors.*' => ['string', 'max:100'],

            // Paths section
            'paths' => ['sometimes', 'array'],
            'paths.ignore' => ['sometimes', 'array', 'max:100'],
            'paths.ignore.*' => ['string', 'max:'.self::MAX_PATTERN_LENGTH],
            'paths.include' => ['sometimes', 'array', 'max:100'],
            'paths.include.*' => ['string', 'max:'.self::MAX_PATTERN_LENGTH],
            'paths.sensitive' => ['sometimes', 'array', 'max:50'],
            'paths.sensitive.*' => ['string', 'max:'.self::MAX_PATTERN_LENGTH],

            // Review section
            'review' => ['sometimes', 'array'],
            'review.min_severity' => ['sometimes', Rule::in(SentinelConfigSeverity::values())],
            'review.max_findings' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_MAX_FINDINGS],
            'review.categories' => ['sometimes', 'array'],
            'review.categories.security' => ['sometimes', 'boolean'],
            'review.categories.correctness' => ['sometimes', 'boolean'],
            'review.categories.performance' => ['sometimes', 'boolean'],
            'review.categories.maintainability' => ['sometimes', 'boolean'],
            'review.categories.style' => ['sometimes', 'boolean'],
            'review.categories.testing' => ['sometimes', 'boolean'],
            'review.categories.documentation' => ['sometimes', 'boolean'],
            'review.tone' => ['sometimes', Rule::in(SentinelConfigTone::values())],
            'review.language' => ['sometimes', 'string', 'size:2'],
            'review.focus' => ['sometimes', 'array', 'max:'.self::MAX_FOCUS_ITEMS],
            'review.focus.*' => ['string', 'max:500'],

            // Guidelines section
            'guidelines' => ['sometimes', 'array', 'max:'.self::MAX_GUIDELINES],
            'guidelines.*.path' => ['required_with:guidelines', 'string', 'max:500'],
            'guidelines.*.description' => ['sometimes', 'nullable', 'string', 'max:1000'],

            // Annotations section
            'annotations' => ['sometimes', 'array'],
            'annotations.style' => ['sometimes', Rule::in(AnnotationStyle::values())],
            'annotations.post_threshold' => ['sometimes', Rule::in(SentinelConfigSeverity::values())],
            'annotations.grouped' => ['sometimes', 'boolean'],
            'annotations.include_suggestions' => ['sometimes', 'boolean'],

            // Provider section
            'provider' => ['sometimes', 'array'],
            'provider.preferred' => ['sometimes', 'nullable', Rule::in(AiProvider::values())],
            'provider.model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'provider.fallback' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'The configuration version is required.',
            'version.min' => 'The configuration version must be at least '.self::MIN_VERSION.'.',
            'version.max' => 'The configuration version must not exceed '.self::MAX_VERSION.'.',
            'triggers.target_branches.max' => 'Too many target branches specified (maximum 50).',
            'review.max_findings.max' => 'Maximum findings cannot exceed '.self::MAX_MAX_FINDINGS.'.',
            'review.language.size' => 'Language must be a 2-character ISO 639-1 code.',
            'guidelines.max' => 'Too many guidelines specified (maximum '.self::MAX_GUIDELINES.').',
            'guidelines.*.path.required_with' => 'Each guideline must have a path.',
        ];
    }
}
