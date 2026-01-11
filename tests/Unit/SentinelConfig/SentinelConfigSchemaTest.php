<?php

declare(strict_types=1);

use App\Services\SentinelConfig\SentinelConfigSchema;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->schema = new SentinelConfigSchema();
});

describe('version validation', function (): void {
    it('requires version field', function (): void {
        $this->schema->validate([]);
    })->throws(ValidationException::class);

    it('validates version is within allowed range', function (): void {
        $this->schema->validate(['version' => 1]);
        expect(true)->toBeTrue();
    });

    it('rejects version below minimum', function (): void {
        $this->schema->validate(['version' => 0]);
    })->throws(ValidationException::class);

    it('rejects version above maximum', function (): void {
        $this->schema->validate(['version' => 999]);
    })->throws(ValidationException::class);
});

describe('triggers validation', function (): void {
    it('accepts valid triggers section', function (): void {
        $this->schema->validate([
            'version' => 1,
            'triggers' => [
                'target_branches' => ['main', 'develop'],
                'skip_source_branches' => ['dependabot/*'],
                'skip_labels' => ['wip'],
                'skip_authors' => ['bot'],
            ],
        ]);

        expect(true)->toBeTrue();
    });

    it('validates target_branches max count', function (): void {
        $this->schema->validate([
            'version' => 1,
            'triggers' => [
                'target_branches' => array_fill(0, 51, 'branch'),
            ],
        ]);
    })->throws(ValidationException::class);

    it('validates branch pattern max length', function (): void {
        $this->schema->validate([
            'version' => 1,
            'triggers' => [
                'target_branches' => [str_repeat('a', 257)],
            ],
        ]);
    })->throws(ValidationException::class);
});

describe('paths validation', function (): void {
    it('accepts valid paths section', function (): void {
        $this->schema->validate([
            'version' => 1,
            'paths' => [
                'ignore' => ['*.lock', 'vendor/**'],
                'include' => ['src/**'],
                'sensitive' => ['**/auth/**'],
            ],
        ]);

        expect(true)->toBeTrue();
    });

    it('validates ignore patterns max count', function (): void {
        $this->schema->validate([
            'version' => 1,
            'paths' => [
                'ignore' => array_fill(0, 101, '*.txt'),
            ],
        ]);
    })->throws(ValidationException::class);
});

describe('review validation', function (): void {
    it('accepts valid review section', function (): void {
        $this->schema->validate([
            'version' => 1,
            'review' => [
                'min_severity' => 'medium',
                'max_findings' => 50,
                'tone' => 'constructive',
                'language' => 'en',
                'focus' => ['Security best practices'],
            ],
        ]);

        expect(true)->toBeTrue();
    });

    it('validates min_severity enum', function (): void {
        $this->schema->validate([
            'version' => 1,
            'review' => [
                'min_severity' => 'invalid',
            ],
        ]);
    })->throws(ValidationException::class);

    it('validates max_findings bounds', function (): void {
        $this->schema->validate([
            'version' => 1,
            'review' => [
                'max_findings' => 101,
            ],
        ]);
    })->throws(ValidationException::class);

    it('validates max_findings minimum', function (): void {
        $this->schema->validate([
            'version' => 1,
            'review' => [
                'max_findings' => 0,
            ],
        ]);
    })->throws(ValidationException::class);

    it('validates tone enum', function (): void {
        $this->schema->validate([
            'version' => 1,
            'review' => [
                'tone' => 'aggressive',
            ],
        ]);
    })->throws(ValidationException::class);

    it('validates language code length', function (): void {
        $this->schema->validate([
            'version' => 1,
            'review' => [
                'language' => 'eng',
            ],
        ]);
    })->throws(ValidationException::class);

    it('validates focus array max count', function (): void {
        $this->schema->validate([
            'version' => 1,
            'review' => [
                'focus' => array_fill(0, 21, 'focus item'),
            ],
        ]);
    })->throws(ValidationException::class);

    it('validates categories booleans', function (): void {
        $this->schema->validate([
            'version' => 1,
            'review' => [
                'categories' => [
                    'security' => true,
                    'correctness' => false,
                ],
            ],
        ]);

        expect(true)->toBeTrue();
    });
});

describe('guidelines validation', function (): void {
    it('accepts valid guidelines', function (): void {
        $this->schema->validate([
            'version' => 1,
            'guidelines' => [
                ['path' => 'docs/STANDARDS.md', 'description' => 'Team standards'],
                ['path' => 'docs/SECURITY.md'],
            ],
        ]);

        expect(true)->toBeTrue();
    });

    it('validates guidelines max count', function (): void {
        $guidelines = [];
        for ($i = 0; $i < 11; $i++) {
            $guidelines[] = ['path' => "docs/guide{$i}.md"];
        }

        $this->schema->validate([
            'version' => 1,
            'guidelines' => $guidelines,
        ]);
    })->throws(ValidationException::class);

    it('requires path in guideline entries', function (): void {
        $this->schema->validate([
            'version' => 1,
            'guidelines' => [
                ['description' => 'Missing path'],
            ],
        ]);
    })->throws(ValidationException::class);
});

describe('annotations validation', function (): void {
    it('accepts valid annotations section', function (): void {
        $this->schema->validate([
            'version' => 1,
            'annotations' => [
                'style' => 'review',
                'post_threshold' => 'high',
                'grouped' => true,
                'include_suggestions' => false,
            ],
        ]);

        expect(true)->toBeTrue();
    });

    it('validates style enum', function (): void {
        $this->schema->validate([
            'version' => 1,
            'annotations' => [
                'style' => 'invalid',
            ],
        ]);
    })->throws(ValidationException::class);

    it('validates post_threshold enum', function (): void {
        $this->schema->validate([
            'version' => 1,
            'annotations' => [
                'post_threshold' => 'invalid',
            ],
        ]);
    })->throws(ValidationException::class);
});

describe('complete configuration', function (): void {
    it('validates full valid configuration', function (): void {
        $this->schema->validate([
            'version' => 1,
            'triggers' => [
                'target_branches' => ['main', 'develop', 'release/*'],
                'skip_source_branches' => ['dependabot/*'],
                'skip_labels' => ['skip-review', 'wip'],
                'skip_authors' => ['dependabot[bot]'],
            ],
            'paths' => [
                'ignore' => ['*.lock', 'docs/**'],
                'include' => ['app/**', 'src/**'],
                'sensitive' => ['**/auth/**'],
            ],
            'review' => [
                'min_severity' => 'low',
                'max_findings' => 25,
                'categories' => [
                    'security' => true,
                    'correctness' => true,
                    'performance' => true,
                    'maintainability' => true,
                    'style' => false,
                ],
                'tone' => 'constructive',
                'language' => 'en',
                'focus' => ['SQL injection prevention'],
            ],
            'guidelines' => [
                ['path' => 'docs/CODING_STANDARDS.md', 'description' => 'Team coding conventions'],
            ],
            'annotations' => [
                'style' => 'review',
                'post_threshold' => 'medium',
                'grouped' => true,
                'include_suggestions' => true,
            ],
        ]);

        expect(true)->toBeTrue();
    });
});

describe('schema constants', function (): void {
    it('has correct version bounds', function (): void {
        expect(SentinelConfigSchema::MIN_VERSION)->toBe(1);
        expect(SentinelConfigSchema::MAX_VERSION)->toBe(1);
    });

    it('has correct limit constants', function (): void {
        expect(SentinelConfigSchema::DEFAULT_MAX_FINDINGS)->toBe(25);
        expect(SentinelConfigSchema::MAX_MAX_FINDINGS)->toBe(100);
        expect(SentinelConfigSchema::MAX_GUIDELINES)->toBe(10);
        expect(SentinelConfigSchema::MAX_FOCUS_ITEMS)->toBe(20);
        expect(SentinelConfigSchema::MAX_PATTERN_LENGTH)->toBe(256);
    });
});
