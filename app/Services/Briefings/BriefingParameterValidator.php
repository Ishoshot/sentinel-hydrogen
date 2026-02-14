<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Models\Briefing;
use App\Services\Briefings\Support\BriefingParameterLimitEnforcer;
use App\Services\Briefings\Support\BriefingParameterSchemaValidator;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class BriefingParameterValidator
{
    /**
     * Create a new briefing parameter validator instance.
     */
    public function __construct(
        private BriefingParameterSchemaValidator $schemaValidator,
        private BriefingParameterLimitEnforcer $limitEnforcer,
    ) {}

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

        $rules = $this->schemaValidator->rules($schema);
        $messages = $this->schemaValidator->messages($schema);

        $validator = Validator::make($parameters, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        $this->limitEnforcer->enforce($validated);

        return BriefingParameters::fromArray($validated);
    }
}
