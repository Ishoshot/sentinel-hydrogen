<?php

declare(strict_types=1);

use App\Enums\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\GuidelinesCollector;
use App\Services\Context\ContextBag;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

it('has correct priority', function (): void {
    $collector = app(GuidelinesCollector::class);

    expect($collector->priority())->toBe(45);
});

it('has correct name', function (): void {
    $collector = app(GuidelinesCollector::class);

    expect($collector->name())->toBe('guidelines');
});

it('should collect when repository and run are provided', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();

    $collector = app(GuidelinesCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($shouldCollect)->toBeTrue();
});

it('should not collect when repository is missing', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->make();

    $collector = app(GuidelinesCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'run' => $run,
    ]);

    expect($shouldCollect)->toBeFalse();
});

it('should not collect when run is missing', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->make();

    $collector = app(GuidelinesCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $repository,
    ]);

    expect($shouldCollect)->toBeFalse();
});

it('validates allowed file extensions', function (string $path, bool $expected): void {
    $collector = app(GuidelinesCollector::class);

    $reflection = new ReflectionClass($collector);
    $method = $reflection->getMethod('isAllowedFileType');
    $method->setAccessible(true);

    $result = $method->invoke($collector, $path);

    expect($result)->toBe($expected);
})->with([
    'markdown file' => ['docs/GUIDELINES.md', true],
    'mdx file' => ['docs/review-rules.mdx', true],
    'blade.php file' => ['templates/rules.blade.php', true],
    'php file' => ['src/Config.php', false],
    'yaml file' => ['config.yaml', false],
    'json file' => ['package.json', false],
    'txt file' => ['README.txt', false],
    'uppercase MD' => ['CONTRIBUTING.MD', true],
    'uppercase MDX' => ['GUIDE.MDX', true],
    'nested path md' => ['.sentinel/guidelines/security.md', true],
]);

it('collects guidelines from sentinel config', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create();

    $guidelineContent = '# Code Review Guidelines\n\nFollow these rules...';

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'test-owner', 'test-repo', 'docs/GUIDELINES.md')
        ->once()
        ->andReturn($guidelineContent);

    /** @var GitHubApiServiceContract $mockGitHubService */
    $collector = new GuidelinesCollector($mockGitHubService);

    $bag = new ContextBag();
    $bag->metadata = [
        'sentinel_config' => [
            'version' => '1.0',
            'guidelines' => [
                ['path' => 'docs/GUIDELINES.md', 'description' => 'Main guidelines'],
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->guidelines)->toHaveCount(1)
        ->and($bag->guidelines[0]['path'])->toBe('docs/GUIDELINES.md')
        ->and($bag->guidelines[0]['description'])->toBe('Main guidelines')
        ->and($bag->guidelines[0]['content'])->toBe($guidelineContent);
});

it('skips non-allowed file types', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldNotReceive('getFileContents');

    /** @var GitHubApiServiceContract $mockGitHubService */
    $collector = new GuidelinesCollector($mockGitHubService);

    $bag = new ContextBag();
    $bag->metadata = [
        'sentinel_config' => [
            'version' => '1.0',
            'guidelines' => [
                ['path' => 'config.yaml', 'description' => 'Not allowed'],
                ['path' => 'src/Rules.php', 'description' => 'Also not allowed'],
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->guidelines)->toBeEmpty();
});

it('limits guidelines to maximum count', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);

    // Create 7 guidelines but only expect 5 to be fetched
    $guidelines = [];
    for ($i = 1; $i <= 7; $i++) {
        $guidelines[] = ['path' => "docs/guide-{$i}.md", 'description' => "Guide {$i}"];

        if ($i <= 5) {
            $mockGitHubService->shouldReceive('getFileContents')
                ->with(12345, 'test-owner', 'test-repo', "docs/guide-{$i}.md")
                ->once()
                ->andReturn("Content for guide {$i}");
        }
    }

    /** @var GitHubApiServiceContract $mockGitHubService */
    $collector = new GuidelinesCollector($mockGitHubService);

    $bag = new ContextBag();
    $bag->metadata = [
        'sentinel_config' => [
            'version' => '1.0',
            'guidelines' => $guidelines,
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->guidelines)->toHaveCount(5);
});

it('handles missing guideline files gracefully', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'test-owner', 'test-repo', 'docs/missing.md')
        ->once()
        ->andThrow(new Exception('File not found'));
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'test-owner', 'test-repo', 'docs/exists.md')
        ->once()
        ->andReturn('Existing content');

    /** @var GitHubApiServiceContract $mockGitHubService */
    $collector = new GuidelinesCollector($mockGitHubService);

    $bag = new ContextBag();
    $bag->metadata = [
        'sentinel_config' => [
            'version' => '1.0',
            'guidelines' => [
                ['path' => 'docs/missing.md', 'description' => 'Missing file'],
                ['path' => 'docs/exists.md', 'description' => 'Exists'],
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->guidelines)->toHaveCount(1)
        ->and($bag->guidelines[0]['path'])->toBe('docs/exists.md');
});

it('handles base64 encoded response from GitHub', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create();

    $originalContent = '# Guidelines Content';
    $base64Content = base64_encode($originalContent);

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'test-owner', 'test-repo', 'docs/GUIDELINES.md')
        ->once()
        ->andReturn([
            'content' => $base64Content,
            'encoding' => 'base64',
        ]);

    /** @var GitHubApiServiceContract $mockGitHubService */
    $collector = new GuidelinesCollector($mockGitHubService);

    $bag = new ContextBag();
    $bag->metadata = [
        'sentinel_config' => [
            'version' => '1.0',
            'guidelines' => [
                ['path' => 'docs/GUIDELINES.md', 'description' => 'Test'],
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->guidelines)->toHaveCount(1)
        ->and($bag->guidelines[0]['content'])->toBe($originalContent);
});

it('does nothing when no guidelines configured', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldNotReceive('getFileContents');

    /** @var GitHubApiServiceContract $mockGitHubService */
    $collector = new GuidelinesCollector($mockGitHubService);

    $bag = new ContextBag();
    $bag->metadata = [
        'sentinel_config' => [
            'version' => '1.0',
            'guidelines' => [],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->guidelines)->toBeEmpty();
});

it('does nothing when sentinel config is not in metadata', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldNotReceive('getFileContents');

    /** @var GitHubApiServiceContract $mockGitHubService */
    $collector = new GuidelinesCollector($mockGitHubService);

    $bag = new ContextBag();
    $bag->metadata = [];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->guidelines)->toBeEmpty();
});

it('truncates oversized content', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'test-owner/test-repo',
    ]);
    $run = Run::factory()->forRepository($repository)->create();

    // Create content larger than 50KB
    $largeContent = str_repeat('A', 60000);

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'test-owner', 'test-repo', 'docs/large.md')
        ->once()
        ->andReturn($largeContent);

    /** @var GitHubApiServiceContract $mockGitHubService */
    $collector = new GuidelinesCollector($mockGitHubService);

    $bag = new ContextBag();
    $bag->metadata = [
        'sentinel_config' => [
            'version' => '1.0',
            'guidelines' => [
                ['path' => 'docs/large.md', 'description' => 'Large file'],
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->guidelines)->toHaveCount(1)
        ->and(mb_strlen($bag->guidelines[0]['content'], '8bit'))->toBeLessThan(60000)
        ->and($bag->guidelines[0]['content'])->toContain('[large.md truncated due to size limit]');
});
