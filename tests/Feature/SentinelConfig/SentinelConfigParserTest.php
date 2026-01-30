<?php

declare(strict_types=1);

use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Enums\Reviews\AnnotationStyle;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Enums\SentinelConfig\SentinelConfigTone;
use App\Exceptions\SentinelConfig\ConfigParseException;
use App\Exceptions\SentinelConfig\ConfigValidationException;
use App\Services\SentinelConfig\Contracts\SentinelConfigParser;
use App\Services\SentinelConfig\SentinelConfigParserService;
use App\Services\SentinelConfig\SentinelConfigSchema;

beforeEach(function (): void {
    $this->parser = new SentinelConfigParserService(new SentinelConfigSchema());
});

describe('YAML parsing', function (): void {
    it('parses minimal valid YAML', function (): void {
        $yaml = 'version: 1';

        $config = $this->parser->parse($yaml);

        expect($config)->toBeInstanceOf(SentinelConfig::class);
        expect($config->version)->toBe(1);
    });

    it('parses complete configuration', function (): void {
        $yaml = <<<'YAML'
version: 1

triggers:
  target_branches:
    - main
    - develop
  skip_labels:
    - skip-review

paths:
  ignore:
    - "*.lock"
  sensitive:
    - "**/auth/**"

review:
  min_severity: medium
  max_findings: 30
  tone: educational
  language: en
  focus:
    - "Security best practices"

guidelines:
  - path: docs/STANDARDS.md
    description: Team coding standards

annotations:
  style: review
  grouped: true
YAML;

        $config = $this->parser->parse($yaml);

        expect($config->version)->toBe(1);
        expect($config->triggers->targetBranches)->toBe(['main', 'develop']);
        expect($config->triggers->skipLabels)->toBe(['skip-review']);
        expect($config->paths->ignore)->toBe(['*.lock']);
        expect($config->paths->sensitive)->toBe(['**/auth/**']);
        expect($config->review->minSeverity)->toBe(SentinelConfigSeverity::Medium);
        expect($config->review->maxFindings)->toBe(30);
        expect($config->review->tone)->toBe(SentinelConfigTone::Educational);
        expect($config->review->focus)->toBe(['Security best practices']);
        expect($config->guidelines)->toHaveCount(1);
        expect($config->guidelines[0]->path)->toBe('docs/STANDARDS.md');
        expect($config->annotations->style)->toBe(AnnotationStyle::Review);
        expect($config->annotations->grouped)->toBeTrue();
    });

    it('throws ConfigParseException for empty content', function (): void {
        $this->parser->parse('');
    })->throws(ConfigParseException::class, 'Configuration file is empty');

    it('throws ConfigParseException for whitespace-only content', function (): void {
        $this->parser->parse("   \n\t   ");
    })->throws(ConfigParseException::class, 'Configuration file is empty');

    it('throws ConfigParseException for invalid YAML syntax', function (): void {
        $yaml = "version: 1\n  invalid:\n indent";

        $this->parser->parse($yaml);
    })->throws(ConfigParseException::class, 'YAML syntax error');

    it('throws ConfigParseException for non-object root', function (): void {
        $yaml = <<<'YAML'
- just
- a
- list
YAML;

        $this->parser->parse($yaml);
    })->throws(ConfigParseException::class, 'Root element must be an object');
});

describe('schema validation', function (): void {
    it('throws ConfigValidationException for missing version', function (): void {
        $yaml = "triggers:\n  target_branches:\n    - main";

        $this->parser->parse($yaml);
    })->throws(ConfigValidationException::class);

    it('throws ConfigValidationException for invalid severity value', function (): void {
        $yaml = "version: 1\nreview:\n  min_severity: invalid";

        $this->parser->parse($yaml);
    })->throws(ConfigValidationException::class);

    it('throws ConfigValidationException for invalid tone value', function (): void {
        $yaml = "version: 1\nreview:\n  tone: angry";

        $this->parser->parse($yaml);
    })->throws(ConfigValidationException::class);
});

describe('tryParse method', function (): void {
    it('returns success result for valid YAML', function (): void {
        $result = $this->parser->tryParse('version: 1');

        expect($result['success'])->toBeTrue();
        expect($result['config'])->toBeInstanceOf(SentinelConfig::class);
        expect($result['error'])->toBeNull();
    });

    it('returns failure result for empty content', function (): void {
        $result = $this->parser->tryParse('');

        expect($result['success'])->toBeFalse();
        expect($result['config'])->toBeNull();
        expect($result['error'])->toContain('empty');
    });

    it('returns failure result for invalid YAML', function (): void {
        $result = $this->parser->tryParse("invalid:\n yaml: {");

        expect($result['success'])->toBeFalse();
        expect($result['config'])->toBeNull();
        expect($result['error'])->not->toBeEmpty();
    });

    it('returns failure result for validation errors', function (): void {
        $result = $this->parser->tryParse('version: 999');

        expect($result['success'])->toBeFalse();
        expect($result['config'])->toBeNull();
        expect($result['error'])->toContain('validation failed');
    });
});

describe('container binding', function (): void {
    it('resolves parser from container', function (): void {
        $parser = app(SentinelConfigParser::class);

        expect($parser)->toBeInstanceOf(SentinelConfigParserService::class);
    });
});

describe('real-world configurations', function (): void {
    it('parses configuration with glob patterns', function (): void {
        $yaml = <<<'YAML'
version: 1
triggers:
  target_branches:
    - main
    - "release/*"
    - "hotfix/**"
  skip_source_branches:
    - "dependabot/*"
    - "renovate/*"
paths:
  ignore:
    - "*.lock"
    - "vendor/**"
    - "node_modules/**"
  include:
    - "app/**"
    - "src/**/*.php"
YAML;

        $config = $this->parser->parse($yaml);

        expect($config->triggers->targetBranches)->toBe(['main', 'release/*', 'hotfix/**']);
        expect($config->triggers->skipSourceBranches)->toBe(['dependabot/*', 'renovate/*']);
        expect($config->paths->ignore)->toContain('vendor/**');
    });

    it('parses configuration with all categories', function (): void {
        $yaml = <<<'YAML'
version: 1
review:
  categories:
    security: true
    correctness: true
    performance: false
    maintainability: true
    style: true
YAML;

        $config = $this->parser->parse($yaml);

        expect($config->review->categories->security)->toBeTrue();
        expect($config->review->categories->performance)->toBeFalse();
        expect($config->review->categories->style)->toBeTrue();
    });

    it('parses configuration with multiple guidelines', function (): void {
        $yaml = <<<'YAML'
version: 1
guidelines:
  - path: docs/CODING_STANDARDS.md
    description: Team coding conventions
  - path: docs/SECURITY.md
    description: Security guidelines
  - path: .github/CONTRIBUTING.md
YAML;

        $config = $this->parser->parse($yaml);

        expect($config->guidelines)->toHaveCount(3);
        expect($config->guidelines[0]->description)->toBe('Team coding conventions');
        expect($config->guidelines[2]->description)->toBeNull();
    });
});
