<?php

declare(strict_types=1);

namespace App\Services\SentinelConfig;

use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Exceptions\SentinelConfig\ConfigParseException;
use App\Exceptions\SentinelConfig\ConfigValidationException;
use App\Services\SentinelConfig\Contracts\SentinelConfigParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses and validates .sentinel/config.yaml files.
 */
final readonly class SentinelConfigParserService implements SentinelConfigParser
{
    /**
     * Create a new SentinelConfigParserService instance.
     */
    public function __construct(
        private SentinelConfigSchema $schema,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function parse(string $yamlContent): SentinelConfig
    {
        $data = $this->parseYaml($yamlContent);
        $this->validateSchema($data);

        return SentinelConfig::fromArray($data);
    }

    /**
     * {@inheritdoc}
     */
    public function tryParse(string $yamlContent): array
    {
        try {
            $config = $this->parse($yamlContent);

            return [
                'success' => true,
                'config' => $config,
                'error' => null,
            ];
        } catch (ConfigParseException|ConfigValidationException $e) {
            Log::warning('Sentinel config parsing failed', [
                'exception' => $e::class,
            ]);

            return [
                'success' => false,
                'config' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse YAML string into an array.
     *
     * @return array<string, mixed>
     *
     * @throws ConfigParseException
     */
    private function parseYaml(string $yamlContent): array
    {
        if (mb_trim($yamlContent) === '') {
            throw ConfigParseException::emptyContent();
        }

        try {
            $data = Yaml::parse($yamlContent);

            if (! is_array($data) || array_is_list($data)) {
                throw ConfigParseException::invalidStructure('Root element must be an object');
            }

            /** @var array<string, mixed> $data */
            return $data;
        } catch (ParseException $parseException) {
            throw ConfigParseException::syntaxError($parseException->getMessage(), $parseException->getParsedLine());
        }
    }

    /**
     * Validate parsed data against the schema.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ConfigValidationException
     */
    private function validateSchema(array $data): void
    {
        try {
            $this->schema->validate($data);
        } catch (ValidationException $validationException) {
            /** @var array<string, array<int, string>> $errors */
            $errors = $validationException->errors();
            throw ConfigValidationException::fromValidationErrors($errors);
        }
    }
}
