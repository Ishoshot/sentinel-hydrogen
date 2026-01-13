<?php

declare(strict_types=1);

use App\DataTransferObjects\SentinelConfig\AnnotationsConfig;
use App\DataTransferObjects\SentinelConfig\CategoriesConfig;
use App\DataTransferObjects\SentinelConfig\GuidelineConfig;
use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\DataTransferObjects\SentinelConfig\ReviewConfig;
use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\DataTransferObjects\SentinelConfig\TriggersConfig;
use App\Enums\AnnotationStyle;
use App\Enums\SentinelConfigSeverity;
use App\Enums\SentinelConfigTone;

describe('SentinelConfig', function (): void {
    it('creates from minimal array with only version', function (): void {
        $config = SentinelConfig::fromArray(['version' => 1]);

        expect($config->version)->toBe(1);
        expect($config->triggers)->toBeNull();
        expect($config->paths)->toBeNull();
        expect($config->review)->toBeNull();
        expect($config->guidelines)->toBe([]);
        expect($config->annotations)->toBeNull();
    });

    it('creates from full array with all sections', function (): void {
        $data = [
            'version' => 1,
            'triggers' => [
                'target_branches' => ['main', 'develop'],
                'skip_labels' => ['skip-review'],
            ],
            'paths' => [
                'ignore' => ['*.lock'],
                'sensitive' => ['**/auth/**'],
            ],
            'review' => [
                'min_severity' => 'medium',
                'max_findings' => 10,
                'tone' => 'direct',
            ],
            'guidelines' => [
                ['path' => 'docs/STANDARDS.md', 'description' => 'Team standards'],
            ],
            'annotations' => [
                'style' => 'comment',
                'grouped' => false,
            ],
        ];

        $config = SentinelConfig::fromArray($data);

        expect($config->version)->toBe(1);
        expect($config->triggers)->toBeInstanceOf(TriggersConfig::class);
        expect($config->triggers->targetBranches)->toBe(['main', 'develop']);
        expect($config->paths)->toBeInstanceOf(PathsConfig::class);
        expect($config->paths->ignore)->toBe(['*.lock']);
        expect($config->review)->toBeInstanceOf(ReviewConfig::class);
        expect($config->review->minSeverity)->toBe(SentinelConfigSeverity::Medium);
        expect($config->guidelines)->toHaveCount(1);
        expect($config->guidelines[0]->path)->toBe('docs/STANDARDS.md');
        expect($config->annotations)->toBeInstanceOf(AnnotationsConfig::class);
        expect($config->annotations->style)->toBe(AnnotationStyle::Comment);
    });

    it('creates default configuration', function (): void {
        $config = SentinelConfig::default();

        expect($config->version)->toBe(SentinelConfig::CURRENT_VERSION);
        expect($config->triggers)->toBeInstanceOf(TriggersConfig::class);
        expect($config->paths)->toBeInstanceOf(PathsConfig::class);
        expect($config->review)->toBeInstanceOf(ReviewConfig::class);
        expect($config->guidelines)->toBe([]);
        expect($config->annotations)->toBeInstanceOf(AnnotationsConfig::class);
    });

    it('converts to array and back', function (): void {
        $config = SentinelConfig::default();
        $array = $config->toArray();

        expect($array)->toHaveKey('version');
        expect($array)->toHaveKey('triggers');
        expect($array)->toHaveKey('paths');
        expect($array)->toHaveKey('review');
        expect($array)->toHaveKey('guidelines');
        expect($array)->toHaveKey('annotations');

        $reconstructed = SentinelConfig::fromArray($array);
        expect($reconstructed->version)->toBe($config->version);
    });

    it('returns defaults when sections are null via helper methods', function (): void {
        $config = SentinelConfig::fromArray(['version' => 1]);

        expect($config->getTriggersOrDefault())->toBeInstanceOf(TriggersConfig::class);
        expect($config->getPathsOrDefault())->toBeInstanceOf(PathsConfig::class);
        expect($config->getReviewOrDefault())->toBeInstanceOf(ReviewConfig::class);
        expect($config->getAnnotationsOrDefault())->toBeInstanceOf(AnnotationsConfig::class);
    });
});

describe('TriggersConfig', function (): void {
    it('creates with default values', function (): void {
        $config = TriggersConfig::default();

        expect($config->targetBranches)->toBe(['main', 'master']);
        expect($config->skipSourceBranches)->toBe([]);
        expect($config->skipLabels)->toBe([]);
        expect($config->skipAuthors)->toBe([]);
    });

    it('creates from array', function (): void {
        $config = TriggersConfig::fromArray([
            'target_branches' => ['develop', 'release/*'],
            'skip_source_branches' => ['dependabot/*'],
            'skip_labels' => ['wip', 'skip-review'],
            'skip_authors' => ['dependabot[bot]'],
        ]);

        expect($config->targetBranches)->toBe(['develop', 'release/*']);
        expect($config->skipSourceBranches)->toBe(['dependabot/*']);
        expect($config->skipLabels)->toBe(['wip', 'skip-review']);
        expect($config->skipAuthors)->toBe(['dependabot[bot]']);
    });

    it('converts mixed types to strings', function (): void {
        $config = TriggersConfig::fromArray([
            'target_branches' => [123, 'main', null],
        ]);

        expect($config->targetBranches)->toBe(['123', 'main', '']);
    });

    it('converts to array', function (): void {
        $config = new TriggersConfig(
            targetBranches: ['main'],
            skipLabels: ['draft'],
        );

        $array = $config->toArray();

        expect($array['target_branches'])->toBe(['main']);
        expect($array['skip_labels'])->toBe(['draft']);
    });
});

describe('PathsConfig', function (): void {
    it('creates with default values', function (): void {
        $config = PathsConfig::default();

        expect($config->ignore)->toBe(['*.lock', 'vendor/**', 'node_modules/**']);
        expect($config->include)->toBe([]);
        expect($config->sensitive)->toBe([]);
    });

    it('creates from array', function (): void {
        $config = PathsConfig::fromArray([
            'ignore' => ['*.log', 'tmp/**'],
            'include' => ['src/**', 'app/**'],
            'sensitive' => ['**/credentials/**'],
        ]);

        expect($config->ignore)->toBe(['*.log', 'tmp/**']);
        expect($config->include)->toBe(['src/**', 'app/**']);
        expect($config->sensitive)->toBe(['**/credentials/**']);
    });

    it('handles non-array values gracefully', function (): void {
        $config = PathsConfig::fromArray([
            'ignore' => 'not-an-array',
        ]);

        expect($config->ignore)->toBe([]);
    });
});

describe('ReviewConfig', function (): void {
    it('creates with default values', function (): void {
        $config = ReviewConfig::default();

        expect($config->minSeverity)->toBe(SentinelConfigSeverity::Low);
        expect($config->maxFindings)->toBe(25);
        expect($config->categories)->toBeInstanceOf(CategoriesConfig::class);
        expect($config->tone)->toBe(SentinelConfigTone::Constructive);
        expect($config->language)->toBe('en');
        expect($config->focus)->toBe([]);
    });

    it('creates from array', function (): void {
        $config = ReviewConfig::fromArray([
            'min_severity' => 'high',
            'max_findings' => 50,
            'tone' => 'educational',
            'language' => 'es',
            'focus' => ['SQL injection', 'XSS prevention'],
        ]);

        expect($config->minSeverity)->toBe(SentinelConfigSeverity::High);
        expect($config->maxFindings)->toBe(50);
        expect($config->tone)->toBe(SentinelConfigTone::Educational);
        expect($config->language)->toBe('es');
        expect($config->focus)->toBe(['SQL injection', 'XSS prevention']);
    });

    it('parses nested categories', function (): void {
        $config = ReviewConfig::fromArray([
            'categories' => [
                'security' => true,
                'style' => true,
                'performance' => false,
            ],
        ]);

        expect($config->categories->security)->toBeTrue();
        expect($config->categories->style)->toBeTrue();
        expect($config->categories->performance)->toBeFalse();
    });
});

describe('CategoriesConfig', function (): void {
    it('has correct defaults', function (): void {
        $config = new CategoriesConfig();

        expect($config->security)->toBeTrue();
        expect($config->correctness)->toBeTrue();
        expect($config->performance)->toBeTrue();
        expect($config->maintainability)->toBeTrue();
        expect($config->style)->toBeFalse();
    });

    it('returns enabled categories', function (): void {
        $config = new CategoriesConfig(
            security: true,
            correctness: false,
            performance: true,
            maintainability: false,
            style: true,
            testing: false,
        );

        expect($config->getEnabled())->toBe(['security', 'performance', 'style']);
    });

    it('creates from array with partial data', function (): void {
        $config = CategoriesConfig::fromArray([
            'style' => true,
        ]);

        expect($config->security)->toBeTrue();
        expect($config->style)->toBeTrue();
    });
});

describe('GuidelineConfig', function (): void {
    it('creates from array', function (): void {
        $config = GuidelineConfig::fromArray([
            'path' => 'docs/GUIDELINES.md',
            'description' => 'Our coding guidelines',
        ]);

        expect($config->path)->toBe('docs/GUIDELINES.md');
        expect($config->description)->toBe('Our coding guidelines');
    });

    it('handles missing description', function (): void {
        $config = GuidelineConfig::fromArray([
            'path' => 'docs/STANDARDS.md',
        ]);

        expect($config->path)->toBe('docs/STANDARDS.md');
        expect($config->description)->toBeNull();
    });

    it('converts to array', function (): void {
        $config = new GuidelineConfig('test.md', 'Test description');
        $array = $config->toArray();

        expect($array['path'])->toBe('test.md');
        expect($array['description'])->toBe('Test description');
    });
});

describe('AnnotationsConfig', function (): void {
    it('creates with default values', function (): void {
        $config = AnnotationsConfig::default();

        expect($config->style)->toBe(AnnotationStyle::Review);
        expect($config->postThreshold)->toBe(SentinelConfigSeverity::Medium);
        expect($config->grouped)->toBeTrue();
        expect($config->includeSuggestions)->toBeTrue();
    });

    it('creates from array', function (): void {
        $config = AnnotationsConfig::fromArray([
            'style' => 'check',
            'post_threshold' => 'critical',
            'grouped' => false,
            'include_suggestions' => false,
        ]);

        expect($config->style)->toBe(AnnotationStyle::Check);
        expect($config->postThreshold)->toBe(SentinelConfigSeverity::Critical);
        expect($config->grouped)->toBeFalse();
        expect($config->includeSuggestions)->toBeFalse();
    });
});
