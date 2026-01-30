<?php

declare(strict_types=1);

use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\Services\Context\ContextBag;
use App\Services\Context\Filters\ConfiguredPathFilter;
use App\Support\PathRuleMatcher;

it('has correct name', function (): void {
    $filter = new ConfiguredPathFilter(new PathRuleMatcher());

    expect($filter->name())->toBe('configured_path');
});

it('has correct order', function (): void {
    $filter = new ConfiguredPathFilter(new PathRuleMatcher());

    expect($filter->order())->toBe(15);
});

it('does nothing when no paths config is present', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/app.php'),
            createTestFile('vendor/package/file.php'),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(2);
});

it('removes files matching ignore patterns', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/app.php'),
            createTestFile('tests/Feature/AppTest.php'),
            createTestFile('docs/README.md'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'ignore' => ['tests/**', 'docs/**'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('src/app.php');
});

it('ignores files matching exact path', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/app.php'),
            createTestFile('README.md'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'ignore' => ['README.md'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('src/app.php');
});

it('applies include patterns as allowlist', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/Controllers/UserController.php'),
            createTestFile('src/Models/User.php'),
            createTestFile('tests/Unit/UserTest.php'),
            createTestFile('config/app.php'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'include' => ['src/**'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(2)
        ->and($bag->files[0]['filename'])->toBe('src/Controllers/UserController.php')
        ->and($bag->files[1]['filename'])->toBe('src/Models/User.php');
});

it('combines ignore and include patterns', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/Controllers/UserController.php'),
            createTestFile('src/Models/User.php'),
            createTestFile('src/Generated/Routes.php'),
            createTestFile('tests/Unit/UserTest.php'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'ignore' => ['src/Generated/**'],
                'include' => ['src/**'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(2)
        ->and($bag->files[0]['filename'])->toBe('src/Controllers/UserController.php')
        ->and($bag->files[1]['filename'])->toBe('src/Models/User.php');
});

it('filters file contents, semantics, guidelines, and repository context by path rules', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/app.php'),
            createTestFile('tests/AppTest.php'),
            createTestFile('docs/Guide.md'),
        ],
        fileContents: [
            'src/app.php' => 'code',
            'tests/AppTest.php' => 'test',
            'docs/Guide.md' => 'docs',
        ],
        semantics: [
            'src/app.php' => ['imports' => []],
            'tests/AppTest.php' => ['imports' => []],
        ],
        guidelines: [
            [
                'path' => 'docs/guideline.md',
                'description' => null,
                'content' => 'doc guidelines',
            ],
            [
                'path' => 'src/CONVENTIONS.md',
                'description' => null,
                'content' => 'src guidelines',
            ],
        ],
        repositoryContext: [
            'readme' => 'readme content',
            'contributing' => 'contrib content',
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'ignore' => ['tests/**', 'docs/**'],
                'include' => ['src/**'],
            ])->toArray(),
            'repository_context_paths' => [
                'readme' => 'README.md',
                'contributing' => 'docs/CONTRIBUTING.md',
            ],
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('src/app.php');

    expect($bag->fileContents)->toHaveCount(1)
        ->and($bag->fileContents)->toHaveKey('src/app.php')
        ->and($bag->fileContents)->not->toHaveKey('tests/AppTest.php')
        ->and($bag->fileContents)->not->toHaveKey('docs/Guide.md');

    expect($bag->semantics)->toHaveCount(1)
        ->and($bag->semantics)->toHaveKey('src/app.php')
        ->and($bag->semantics)->not->toHaveKey('tests/AppTest.php');

    expect($bag->guidelines)->toHaveCount(1)
        ->and($bag->guidelines[0]['path'])->toBe('src/CONVENTIONS.md');

    expect($bag->repositoryContext)->toBe([])
        ->and($bag->metadata)->not->toHaveKey('repository_context_paths');
});

it('marks files as sensitive', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/Controllers/UserController.php'),
            createTestFile('src/Services/AuthService.php'),
            createTestFile('config/auth.php'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'sensitive' => ['**/*auth*', '**/*Auth*'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(3)
        ->and($bag->files[0]['is_sensitive'] ?? false)->toBeFalse()
        ->and($bag->files[1]['is_sensitive'] ?? false)->toBeTrue()
        ->and($bag->files[2]['is_sensitive'] ?? false)->toBeTrue();
});

it('tracks sensitive files in metadata', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/app.php'),
            createTestFile('src/Services/PaymentService.php'),
            createTestFile('config/payment.php'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'sensitive' => ['**/*payment*', '**/*Payment*'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->metadata['sensitive_files'])->toBe([
        'src/Services/PaymentService.php',
        'config/payment.php',
    ]);
});

it('supports single wildcard patterns', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('composer.lock'),
            createTestFile('package-lock.json'),
            createTestFile('yarn.lock'),
            createTestFile('src/app.php'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'ignore' => ['*.lock', '*-lock.json'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('src/app.php');
});

it('supports double wildcard patterns', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('vendor/laravel/framework/src/File.php'),
            createTestFile('node_modules/lodash/index.js'),
            createTestFile('src/app.php'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'ignore' => ['vendor/**', 'node_modules/**'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('src/app.php');
});

it('supports question mark wildcard', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('log1.txt'),
            createTestFile('log2.txt'),
            createTestFile('log10.txt'),
            createTestFile('logs.txt'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'ignore' => ['log?.txt'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    // log?.txt matches single char after 'log', so log1, log2, logs are removed
    // log10.txt has two chars after 'log', so it doesn't match
    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('log10.txt');
});

it('recalculates metrics after filtering', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/app.php', additions: 10, deletions: 5),
            createTestFile('tests/AppTest.php', additions: 20, deletions: 10),
            createTestFile('docs/README.md', additions: 30, deletions: 0),
        ],
        metrics: [
            'files_changed' => 3,
            'lines_added' => 60,
            'lines_deleted' => 15,
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'ignore' => ['tests/**', 'docs/**'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->metrics)->toBe([
        'files_changed' => 1,
        'lines_added' => 10,
        'lines_deleted' => 5,
    ]);
});

it('handles empty ignore array', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/app.php'),
            createTestFile('vendor/package.php'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'ignore' => [],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(2);
});

it('handles empty include array as no restriction', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/app.php'),
            createTestFile('tests/AppTest.php'),
            createTestFile('docs/README.md'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'include' => [],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(3);
});

it('handles invalid paths config gracefully', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/app.php'),
        ],
        metadata: [
            'paths_config' => 'invalid',
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->files)->toHaveCount(1);
});

it('does not add sensitive_files metadata when no sensitive files found', function (): void {
    $bag = new ContextBag(
        files: [
            createTestFile('src/app.php'),
        ],
        metadata: [
            'paths_config' => PathsConfig::fromArray([
                'sensitive' => ['**/*secret*'],
            ])->toArray(),
        ],
    );

    $filter = new ConfiguredPathFilter(new PathRuleMatcher());
    $filter->filter($bag);

    expect($bag->metadata)->not->toHaveKey('sensitive_files');
});

/**
 * Helper to create a test file array.
 *
 * @return array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}
 */
function createTestFile(
    string $filename,
    string $status = 'modified',
    int $additions = 10,
    int $deletions = 5,
    ?string $patch = 'test patch'
): array {
    return [
        'filename' => $filename,
        'status' => $status,
        'additions' => $additions,
        'deletions' => $deletions,
        'changes' => $additions + $deletions,
        'patch' => $patch,
    ];
}
