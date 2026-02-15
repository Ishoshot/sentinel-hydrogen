<?php

declare(strict_types=1);

use App\Services\Context\Filters\Support\ConfiguredPathInclusionDecider;
use App\Services\Context\Filters\Support\ConfiguredPathRepositoryContextFilter;
use App\Services\SentinelConfig\ValueObjects\PathsConfig;
use App\Support\PathRuleMatcher;

beforeEach(function (): void {
    $this->matcher = new PathRuleMatcher;
    $this->decider = new ConfiguredPathInclusionDecider($this->matcher);
    $this->filter = new ConfiguredPathRepositoryContextFilter($this->decider);
});

it('returns early with zero removed when repository context is empty', function (): void {
    $result = $this->filter->filter([], ['some' => 'metadata'], new PathsConfig);

    expect($result['repository_context'])->toBe([])
        ->and($result['metadata'])->toBe(['some' => 'metadata'])
        ->and($result['removed'])->toBe(0);
});

it('returns early when metadata has no repository_context_paths', function (): void {
    $context = ['readme' => 'Some readme content'];
    $metadata = ['other_key' => 'value'];

    $result = $this->filter->filter($context, $metadata, new PathsConfig);

    expect($result['repository_context'])->toBe($context)
        ->and($result['removed'])->toBe(0);
});

it('returns early when repository_context_paths is not an array', function (): void {
    $context = ['readme' => 'Some readme content'];
    $metadata = ['repository_context_paths' => 'not_an_array'];

    $result = $this->filter->filter($context, $metadata, new PathsConfig);

    expect($result['repository_context'])->toBe($context)
        ->and($result['removed'])->toBe(0);
});

it('keeps sections when paths are not in ignore list', function (): void {
    $context = [
        'readme' => 'Readme content',
        'contributing' => 'Contributing content',
    ];
    $metadata = [
        'repository_context_paths' => [
            'readme' => 'README.md',
            'contributing' => 'CONTRIBUTING.md',
        ],
    ];
    $pathsConfig = new PathsConfig(ignore: ['docs/**']);

    $result = $this->filter->filter($context, $metadata, $pathsConfig);

    expect($result['repository_context'])->toHaveKey('readme')
        ->and($result['repository_context'])->toHaveKey('contributing')
        ->and($result['removed'])->toBe(0);
});

it('removes readme section when path matches ignore pattern', function (): void {
    $context = [
        'readme' => 'Readme content',
        'contributing' => 'Contributing content',
    ];
    $metadata = [
        'repository_context_paths' => [
            'readme' => 'docs/README.md',
            'contributing' => 'CONTRIBUTING.md',
        ],
    ];
    $pathsConfig = new PathsConfig(ignore: ['docs/**']);

    $result = $this->filter->filter($context, $metadata, $pathsConfig);

    expect($result['repository_context'])->not->toHaveKey('readme')
        ->and($result['repository_context'])->toHaveKey('contributing')
        ->and($result['removed'])->toBe(1);
});

it('removes contributing section when path matches ignore pattern', function (): void {
    $context = [
        'readme' => 'Readme content',
        'contributing' => 'Contributing content',
    ];
    $metadata = [
        'repository_context_paths' => [
            'readme' => 'README.md',
            'contributing' => 'docs/CONTRIBUTING.md',
        ],
    ];
    $pathsConfig = new PathsConfig(ignore: ['docs/**']);

    $result = $this->filter->filter($context, $metadata, $pathsConfig);

    expect($result['repository_context'])->toHaveKey('readme')
        ->and($result['repository_context'])->not->toHaveKey('contributing')
        ->and($result['removed'])->toBe(1);
});

it('removes both sections when both paths match ignore pattern', function (): void {
    $context = [
        'readme' => 'Readme content',
        'contributing' => 'Contributing content',
    ];
    $metadata = [
        'repository_context_paths' => [
            'readme' => 'docs/README.md',
            'contributing' => 'docs/CONTRIBUTING.md',
        ],
    ];
    $pathsConfig = new PathsConfig(ignore: ['docs/**']);

    $result = $this->filter->filter($context, $metadata, $pathsConfig);

    expect($result['repository_context'])->not->toHaveKey('readme')
        ->and($result['repository_context'])->not->toHaveKey('contributing')
        ->and($result['removed'])->toBe(2);
});

it('removes repository_context_paths from metadata when all paths are filtered', function (): void {
    $context = [
        'readme' => 'Readme content',
    ];
    $metadata = [
        'repository_context_paths' => [
            'readme' => 'docs/README.md',
        ],
    ];
    $pathsConfig = new PathsConfig(ignore: ['docs/**']);

    $result = $this->filter->filter($context, $metadata, $pathsConfig);

    expect($result['metadata'])->not->toHaveKey('repository_context_paths');
});

it('keeps repository_context_paths in metadata when some paths remain', function (): void {
    $context = [
        'readme' => 'Readme content',
        'contributing' => 'Contributing content',
    ];
    $metadata = [
        'repository_context_paths' => [
            'readme' => 'README.md',
            'contributing' => 'docs/CONTRIBUTING.md',
        ],
    ];
    $pathsConfig = new PathsConfig(ignore: ['docs/**']);

    $result = $this->filter->filter($context, $metadata, $pathsConfig);

    expect($result['metadata']['repository_context_paths'])->toBe(['readme' => 'README.md']);
});

it('filters sections not matching include patterns', function (): void {
    $context = [
        'readme' => 'Readme content',
        'contributing' => 'Contributing content',
    ];
    $metadata = [
        'repository_context_paths' => [
            'readme' => 'README.md',
            'contributing' => 'CONTRIBUTING.md',
        ],
    ];
    // Only include README.md
    $pathsConfig = new PathsConfig(include: ['README.md']);

    $result = $this->filter->filter($context, $metadata, $pathsConfig);

    expect($result['repository_context'])->toHaveKey('readme')
        ->and($result['repository_context'])->not->toHaveKey('contributing')
        ->and($result['removed'])->toBe(1);
});

it('handles section present in context but missing from paths', function (): void {
    $context = [
        'readme' => 'Readme content',
        'contributing' => 'Contributing content',
    ];
    $metadata = [
        'repository_context_paths' => [
            'readme' => 'README.md',
            // contributing path is missing
        ],
    ];
    $pathsConfig = new PathsConfig(ignore: ['*.md']);

    $result = $this->filter->filter($context, $metadata, $pathsConfig);

    // readme should be filtered (matches *.md), contributing should stay (no path to check)
    expect($result['repository_context'])->not->toHaveKey('readme')
        ->and($result['repository_context'])->toHaveKey('contributing')
        ->and($result['removed'])->toBe(1);
});

it('handles section missing from context but present in paths', function (): void {
    $context = [
        'readme' => 'Readme content',
    ];
    $metadata = [
        'repository_context_paths' => [
            'readme' => 'README.md',
            'contributing' => 'CONTRIBUTING.md',
        ],
    ];
    $pathsConfig = new PathsConfig(ignore: ['CONTRIBUTING.md']);

    $result = $this->filter->filter($context, $metadata, $pathsConfig);

    // contributing not in context, so nothing to filter
    expect($result['repository_context'])->toHaveKey('readme')
        ->and($result['removed'])->toBe(0);
});
