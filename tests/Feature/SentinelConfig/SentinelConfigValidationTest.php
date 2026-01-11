<?php

declare(strict_types=1);

use App\Exceptions\SentinelConfig\ConfigValidationException;
use App\Services\SentinelConfig\Contracts\SentinelConfigParser;

beforeEach(function (): void {
    $this->parser = app(SentinelConfigParser::class);
});

describe('end-to-end validation', function (): void {
    it('validates complete real-world config', function (): void {
        $yaml = <<<'YAML'
version: 1

triggers:
  target_branches:
    - main
    - develop
    - "release/*"
  skip_source_branches:
    - "dependabot/*"
  skip_labels:
    - skip-review
    - wip
  skip_authors:
    - "dependabot[bot]"

paths:
  ignore:
    - "*.lock"
    - "docs/**"
  include:
    - "app/**"
    - "src/**"
  sensitive:
    - "**/auth/**"

review:
  min_severity: low
  max_findings: 25
  categories:
    security: true
    correctness: true
    performance: true
    maintainability: true
    style: false
  tone: constructive
  language: en
  focus:
    - "SQL injection prevention"

guidelines:
  - path: docs/CODING_STANDARDS.md
    description: "Team coding conventions"

annotations:
  style: review
  post_threshold: medium
  grouped: true
  include_suggestions: true
YAML;

        $config = $this->parser->parse($yaml);

        expect($config->version)->toBe(1);
        expect($config->getTriggersOrDefault()->targetBranches)->toBe(['main', 'develop', 'release/*']);
        expect($config->getReviewOrDefault()->maxFindings)->toBe(25);
    });
});

describe('error messages', function (): void {
    it('provides clear error for invalid severity', function (): void {
        $yaml = "version: 1\nreview:\n  min_severity: super_high";

        try {
            $this->parser->parse($yaml);
            $this->fail('Expected ConfigValidationException');
        } catch (ConfigValidationException $e) {
            expect($e->getMessage())->toContain('validation failed');
            expect($e->getErrors())->toHaveKey('review.min_severity');
        }
    });

    it('provides clear error for invalid tone', function (): void {
        $yaml = "version: 1\nreview:\n  tone: harsh";

        try {
            $this->parser->parse($yaml);
            $this->fail('Expected ConfigValidationException');
        } catch (ConfigValidationException $e) {
            expect($e->getErrors())->toHaveKey('review.tone');
        }
    });

    it('provides clear error for language code', function (): void {
        $yaml = "version: 1\nreview:\n  language: english";

        try {
            $this->parser->parse($yaml);
            $this->fail('Expected ConfigValidationException');
        } catch (ConfigValidationException $e) {
            expect($e->getErrors())->toHaveKey('review.language');
            expect($e->getMessage())->toContain('2-character');
        }
    });

    it('provides clear error for max_findings out of bounds', function (): void {
        $yaml = "version: 1\nreview:\n  max_findings: 500";

        try {
            $this->parser->parse($yaml);
            $this->fail('Expected ConfigValidationException');
        } catch (ConfigValidationException $e) {
            expect($e->getErrors())->toHaveKey('review.max_findings');
        }
    });

    it('provides clear error for too many guidelines', function (): void {
        $guidelines = '';
        for ($i = 0; $i < 15; $i++) {
            $guidelines .= "  - path: guide{$i}.md\n";
        }
        $yaml = "version: 1\nguidelines:\n{$guidelines}";

        try {
            $this->parser->parse($yaml);
            $this->fail('Expected ConfigValidationException');
        } catch (ConfigValidationException $e) {
            expect($e->getErrors())->toHaveKey('guidelines');
        }
    });

    it('aggregates multiple errors', function (): void {
        $yaml = <<<'YAML'
version: 1
review:
  min_severity: invalid
  tone: invalid
  language: too_long
YAML;

        try {
            $this->parser->parse($yaml);
            $this->fail('Expected ConfigValidationException');
        } catch (ConfigValidationException $e) {
            $errors = $e->getErrors();
            expect($errors)->toHaveKey('review.min_severity');
            expect($errors)->toHaveKey('review.tone');
            expect($errors)->toHaveKey('review.language');
        }
    });
});

describe('edge cases', function (): void {
    it('handles empty arrays gracefully', function (): void {
        $yaml = <<<'YAML'
version: 1
triggers:
  target_branches: []
paths:
  ignore: []
review:
  focus: []
guidelines: []
YAML;

        $config = $this->parser->parse($yaml);

        expect($config->triggers->targetBranches)->toBe([]);
        expect($config->paths->ignore)->toBe([]);
        expect($config->review->focus)->toBe([]);
        expect($config->guidelines)->toBe([]);
    });

    it('handles special characters in strings', function (): void {
        $yaml = <<<'YAML'
version: 1
triggers:
  target_branches:
    - "main"
    - "release/v1.0.0"
    - "feature/[A-Z]+-*"
review:
  focus:
    - "Handle 'quoted' strings"
    - "Handle \"double quotes\""
YAML;

        $config = $this->parser->parse($yaml);

        expect($config->triggers->targetBranches)->toContain('feature/[A-Z]+-*');
        expect($config->review->focus)->toContain("Handle 'quoted' strings");
    });

    it('handles unicode in focus areas', function (): void {
        $yaml = <<<'YAML'
version: 1
review:
  focus:
    - "Sécurité des données"
    - "性能优化"
YAML;

        $config = $this->parser->parse($yaml);

        expect($config->review->focus)->toBe(['Sécurité des données', '性能优化']);
    });
});

describe('severity levels', function (): void {
    it('accepts all valid severity values', function (string $severity): void {
        $yaml = "version: 1\nreview:\n  min_severity: {$severity}";

        $config = $this->parser->parse($yaml);

        expect($config->review->minSeverity->value)->toBe($severity);
    })->with(['critical', 'high', 'medium', 'low', 'info']);

    it('accepts all valid annotation thresholds', function (string $severity): void {
        $yaml = "version: 1\nannotations:\n  post_threshold: {$severity}";

        $config = $this->parser->parse($yaml);

        expect($config->annotations->postThreshold->value)->toBe($severity);
    })->with(['critical', 'high', 'medium', 'low', 'info']);
});

describe('tone options', function (): void {
    it('accepts all valid tone values', function (string $tone): void {
        $yaml = "version: 1\nreview:\n  tone: {$tone}";

        $config = $this->parser->parse($yaml);

        expect($config->review->tone->value)->toBe($tone);
    })->with(['constructive', 'direct', 'educational', 'minimal']);
});

describe('annotation styles', function (): void {
    it('accepts all valid annotation styles', function (string $style): void {
        $yaml = "version: 1\nannotations:\n  style: {$style}";

        $config = $this->parser->parse($yaml);

        expect($config->annotations->style->value)->toBe($style);
    })->with(['review', 'comment', 'check']);
});
