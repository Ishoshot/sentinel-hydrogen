<?php

declare(strict_types=1);

namespace App\Services\SentinelConfig\Contracts;

use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Exceptions\SentinelConfig\ConfigParseException;
use App\Exceptions\SentinelConfig\ConfigValidationException;

/**
 * Contract for parsing .sentinel/config.yaml files.
 */
interface SentinelConfigParser
{
    /**
     * Parse YAML content into a validated SentinelConfig DTO.
     *
     * @throws ConfigParseException If YAML syntax is invalid
     * @throws ConfigValidationException If schema validation fails
     */
    public function parse(string $yamlContent): SentinelConfig;

    /**
     * Parse YAML content with graceful error handling.
     *
     * Returns a result array containing either the config or error details.
     *
     * @return array{success: bool, config: SentinelConfig|null, error: string|null}
     */
    public function tryParse(string $yamlContent): array;
}
