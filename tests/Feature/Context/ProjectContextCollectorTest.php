<?php

declare(strict_types=1);

use App\Enums\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\ProjectContextCollector;
use App\Services\Context\ContextBag;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\GitHubApiService;

it('has correct priority', function (): void {
    $collector = app(ProjectContextCollector::class);

    expect($collector->priority())->toBe(55);
});

it('has correct name', function (): void {
    $collector = app(ProjectContextCollector::class);

    expect($collector->name())->toBe('project_context');
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

    $collector = app(ProjectContextCollector::class);

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

    $collector = app(ProjectContextCollector::class);

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

    $collector = app(ProjectContextCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $repository,
    ]);

    expect($shouldCollect)->toBeFalse();
});

it('collects PHP composer.json dependencies', function (): void {
    $composerJson = json_encode([
        'require' => [
            'php' => '^8.2',
            'laravel/framework' => '^11.0',
            'guzzlehttp/guzzle' => '^7.8',
        ],
        'require-dev' => [
            'pestphp/pest' => '^2.0',
        ],
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'composer.json')
        ->once()
        ->andReturn($composerJson);

    // Return 404-like error for other files (they don't exist)
    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->projectContext)->not->toBeEmpty()
        ->and($bag->projectContext['languages'])->toContain('php')
        ->and($bag->projectContext['runtime'])->toBe(['name' => 'PHP', 'version' => '^8.2'])
        ->and($bag->projectContext['frameworks'])->toContain(['name' => 'Laravel', 'version' => '^11.0']);

    // Check dependencies
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    expect($depNames)->toContain('laravel/framework')
        ->and($depNames)->toContain('guzzlehttp/guzzle')
        ->and($depNames)->toContain('pestphp/pest');

    // Check dev dependency is marked
    $pestDep = collect($bag->projectContext['dependencies'])->firstWhere('name', 'pestphp/pest');
    expect($pestDep['dev'])->toBeTrue();
});

it('collects JavaScript package.json dependencies', function (): void {
    $packageJson = json_encode([
        'engines' => [
            'node' => '>=18.0.0',
        ],
        'dependencies' => [
            'react' => '^18.2.0',
            'next' => '^14.0.0',
        ],
        'devDependencies' => [
            'typescript' => '^5.0.0',
        ],
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);

    // No composer.json
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'composer.json')
        ->once()
        ->andThrow(new RuntimeException('File not found'));

    // Return package.json
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'package.json')
        ->once()
        ->andReturn($packageJson);

    // All other files not found
    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->projectContext)->not->toBeEmpty()
        ->and($bag->projectContext['languages'])->toContain('javascript')
        ->and($bag->projectContext['runtime'])->toBe(['name' => 'Node.js', 'version' => '>=18.0.0']);

    // Check frameworks
    $frameworkNames = array_column($bag->projectContext['frameworks'], 'name');
    expect($frameworkNames)->toContain('React')
        ->and($frameworkNames)->toContain('Next.js');

    // Check dependencies
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    expect($depNames)->toContain('react')
        ->and($depNames)->toContain('next')
        ->and($depNames)->toContain('typescript');
});

it('collects Go go.mod dependencies', function (): void {
    $goMod = <<<'GOMOD'
module github.com/example/myapp

go 1.21

require (
    github.com/gin-gonic/gin v1.9.1
    github.com/go-redis/redis/v8 v8.11.5
)
GOMOD;

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);

    // Return go.mod
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'go.mod')
        ->once()
        ->andReturn($goMod);

    // All other files not found
    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->projectContext)->not->toBeEmpty()
        ->and($bag->projectContext['languages'])->toContain('go')
        ->and($bag->projectContext['runtime'])->toBe(['name' => 'Go', 'version' => '1.21']);

    // Check frameworks
    $frameworkNames = array_column($bag->projectContext['frameworks'], 'name');
    expect($frameworkNames)->toContain('Gin');

    // Check dependencies
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    expect($depNames)->toContain('github.com/gin-gonic/gin')
        ->and($depNames)->toContain('github.com/go-redis/redis/v8');
});

it('collects Python requirements.txt dependencies', function (): void {
    $requirements = <<<'REQUIREMENTS'
django==4.2.0
celery>=5.3.0
redis~=4.5
# This is a comment
-e git+https://github.com/example/pkg.git#egg=pkg
flask
REQUIREMENTS;

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);

    // Return requirements.txt
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'requirements.txt')
        ->once()
        ->andReturn($requirements);

    // All other files not found
    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->projectContext)->not->toBeEmpty()
        ->and($bag->projectContext['languages'])->toContain('python');

    // Check frameworks
    $frameworkNames = array_column($bag->projectContext['frameworks'], 'name');
    expect($frameworkNames)->toContain('Django')
        ->and($frameworkNames)->toContain('Flask');

    // Check dependencies
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    expect($depNames)->toContain('django')
        ->and($depNames)->toContain('celery')
        ->and($depNames)->toContain('redis')
        ->and($depNames)->toContain('flask');

    // Django should have version
    $djangoDep = collect($bag->projectContext['dependencies'])->firstWhere('name', 'django');
    expect($djangoDep['version'])->toBe('==4.2.0');
});

it('collects Rust Cargo.toml dependencies', function (): void {
    $cargoToml = <<<'CARGO'
[package]
name = "myapp"
version = "0.1.0"
rust-version = "1.70"

[dependencies]
actix-web = "4.4.0"
serde = { version = "1.0", features = ["derive"] }
tokio = "1.32"

[dev-dependencies]
criterion = "0.5"
CARGO;

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);

    // Return Cargo.toml
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'Cargo.toml')
        ->once()
        ->andReturn($cargoToml);

    // All other files not found
    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->projectContext)->not->toBeEmpty()
        ->and($bag->projectContext['languages'])->toContain('rust')
        ->and($bag->projectContext['runtime'])->toBe(['name' => 'Rust', 'version' => '1.70']);

    // Check frameworks
    $frameworkNames = array_column($bag->projectContext['frameworks'], 'name');
    expect($frameworkNames)->toContain('Actix Web');

    // Check dependencies
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    expect($depNames)->toContain('actix-web')
        ->and($depNames)->toContain('serde')
        ->and($depNames)->toContain('tokio')
        ->and($depNames)->toContain('criterion');

    // Check dev dependency is marked
    $criterionDep = collect($bag->projectContext['dependencies'])->firstWhere('name', 'criterion');
    expect($criterionDep['dev'])->toBeTrue();
});

it('does not set project context when no manifest files are found', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);

    // All files not found
    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->projectContext)->toBeEmpty();
});

it('collects from multiple ecosystems in a monorepo', function (): void {
    $composerJson = json_encode([
        'require' => [
            'php' => '^8.2',
            'laravel/framework' => '^11.0',
        ],
    ]);

    $packageJson = json_encode([
        'dependencies' => [
            'vue' => '^3.4.0',
        ],
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);

    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'composer.json')
        ->once()
        ->andReturn($composerJson);

    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'package.json')
        ->once()
        ->andReturn($packageJson);

    // All other files not found
    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->projectContext)->not->toBeEmpty()
        ->and($bag->projectContext['languages'])->toContain('php')
        ->and($bag->projectContext['languages'])->toContain('javascript');

    // Check both frameworks detected
    $frameworkNames = array_column($bag->projectContext['frameworks'], 'name');
    expect($frameworkNames)->toContain('Laravel')
        ->and($frameworkNames)->toContain('Vue.js');
});

it('handles base64 encoded response from GitHub', function (): void {
    $composerJson = json_encode([
        'require' => [
            'php' => '^8.2',
            'laravel/framework' => '^11.0',
        ],
    ]);

    $base64Content = base64_encode($composerJson);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);

    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'composer.json')
        ->once()
        ->andReturn([
            'content' => $base64Content,
            'encoding' => 'base64',
        ]);

    // All other files not found
    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->projectContext)->not->toBeEmpty()
        ->and($bag->projectContext['languages'])->toContain('php')
        ->and($bag->projectContext['frameworks'])->toContain(['name' => 'Laravel', 'version' => '^11.0']);
});

it('prioritizes dependencies that match semantic imports', function (): void {
    // Composer with many dependencies but we only use guzzlehttp/guzzle
    $composerJson = json_encode([
        'require' => [
            'php' => '^8.2',
            'laravel/framework' => '^11.0',
            'guzzlehttp/guzzle' => '^7.8',
            'league/flysystem' => '^3.0',
            'monolog/monolog' => '^3.5',
            'ramsey/uuid' => '^4.7',
        ],
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'composer.json')
        ->once()
        ->andReturn($composerJson);

    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);

    // Set up semantic data with imports - we use Illuminate (Laravel), GuzzleHttp, and Monolog
    $bag = new ContextBag();
    $bag->semantics = [
        'app/Services/ApiClient.php' => [
            'language' => 'php',
            'imports' => [
                ['module' => 'Illuminate\\Support\\Facades\\Log'],
                ['module' => 'GuzzleHttp\\Client'],
                ['module' => 'Monolog\\Logger'],
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    // Get the first few dependencies (used ones should come first)
    $depNames = array_column($bag->projectContext['dependencies'], 'name');

    // laravel/framework, guzzlehttp/guzzle, and monolog/monolog should be in the first 3
    // because they match the imports
    $firstThree = array_slice($depNames, 0, 3);
    expect($firstThree)->toContain('laravel/framework')
        ->and($firstThree)->toContain('guzzlehttp/guzzle')
        ->and($firstThree)->toContain('monolog/monolog');
});

it('ignores internal App namespace imports when filtering', function (): void {
    $composerJson = json_encode([
        'require' => [
            'php' => '^8.2',
            'laravel/framework' => '^11.0',
            'guzzlehttp/guzzle' => '^7.8',
        ],
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'composer.json')
        ->once()
        ->andReturn($composerJson);

    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);

    // Set up semantic data with internal App imports and one external
    $bag = new ContextBag();
    $bag->semantics = [
        'app/Http/Controllers/UserController.php' => [
            'language' => 'php',
            'imports' => [
                ['module' => 'App\\Models\\User'],
                ['module' => 'App\\Services\\UserService'],
                ['module' => 'Tests\\TestCase'],
                ['module' => 'GuzzleHttp\\Client'],
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    // Should still have all dependencies
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    expect($depNames)->toContain('laravel/framework')
        ->and($depNames)->toContain('guzzlehttp/guzzle');

    // guzzlehttp/guzzle should be prioritized because it matches GuzzleHttp import
    $firstDep = $bag->projectContext['dependencies'][0];
    expect($firstDep['name'])->toBe('guzzlehttp/guzzle');
});

it('normalizes Python dotted imports for matching', function (): void {
    $requirements = <<<'REQUIREMENTS'
django==4.2.0
celery>=5.3.0
redis~=4.5
flask==2.0.0
REQUIREMENTS;

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'requirements.txt')
        ->once()
        ->andReturn($requirements);

    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);

    // Set up semantic data with Python dotted imports
    $bag = new ContextBag();
    $bag->semantics = [
        'app/views.py' => [
            'language' => 'python',
            'imports' => [
                ['module' => 'django.http'],
                ['module' => 'django.shortcuts'],
                ['module' => 'celery.task'],
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    // django and celery should be prioritized
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    $firstTwo = array_slice($depNames, 0, 2);

    expect($firstTwo)->toContain('django')
        ->and($firstTwo)->toContain('celery');
});

it('works without semantic data (backward compatibility)', function (): void {
    $composerJson = json_encode([
        'require' => [
            'php' => '^8.2',
            'laravel/framework' => '^11.0',
            'guzzlehttp/guzzle' => '^7.8',
        ],
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'composer.json')
        ->once()
        ->andReturn($composerJson);

    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);

    // Empty semantics - no semantic data available
    $bag = new ContextBag();
    $bag->semantics = [];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    // Should still work - dependencies in manifest order
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    expect($depNames)->toContain('laravel/framework')
        ->and($depNames)->toContain('guzzlehttp/guzzle')
        ->and(count($depNames))->toBe(2);
});

it('normalizes Rust crate imports for matching', function (): void {
    $cargoToml = <<<'CARGO'
[package]
name = "myapp"
version = "0.1.0"

[dependencies]
tokio = "1.32"
serde = "1.0"
actix-web = "4.4.0"
reqwest = "0.11"
CARGO;

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'Cargo.toml')
        ->once()
        ->andReturn($cargoToml);

    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);

    // Set up semantic data with Rust imports using :: notation
    $bag = new ContextBag();
    $bag->semantics = [
        'src/main.rs' => [
            'language' => 'rust',
            'imports' => [
                ['module' => 'tokio::sync::mpsc'],
                ['module' => 'serde::Deserialize'],
                ['module' => 'std::collections::HashMap'], // Should be filtered (std lib)
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    // tokio and serde should be prioritized
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    $firstTwo = array_slice($depNames, 0, 2);

    expect($firstTwo)->toContain('tokio')
        ->and($firstTwo)->toContain('serde');
});

it('normalizes Dart package imports for matching', function (): void {
    $pubspecYaml = <<<'YAML'
name: myapp
environment:
  sdk: '>=3.0.0 <4.0.0'

dependencies:
  flutter:
  http: ^1.1.0
  provider: ^6.0.0
  dio: ^5.3.0
YAML;

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'pubspec.yaml')
        ->once()
        ->andReturn($pubspecYaml);

    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);

    // Set up semantic data with Dart package: imports
    $bag = new ContextBag();
    $bag->semantics = [
        'lib/main.dart' => [
            'language' => 'dart',
            'imports' => [
                ['module' => 'package:flutter/material.dart'],
                ['module' => 'package:dio/dio.dart'],
                ['module' => 'package:provider/provider.dart'],
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    // flutter, dio, provider should be prioritized
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    $firstThree = array_slice($depNames, 0, 3);

    expect($firstThree)->toContain('flutter')
        ->and($firstThree)->toContain('dio')
        ->and($firstThree)->toContain('provider');
});

it('filters out standard library imports from various languages', function (): void {
    $composerJson = json_encode([
        'require' => [
            'php' => '^8.2',
            'laravel/framework' => '^11.0',
            'guzzlehttp/guzzle' => '^7.8',
        ],
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'repo')
        ->create();
    $run = Run::factory()->forRepository($repository)->create();

    $mockGitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubService->shouldReceive('getFileContents')
        ->with(12345, 'owner', 'repo', 'composer.json')
        ->once()
        ->andReturn($composerJson);

    $mockGitHubService->shouldReceive('getFileContents')
        ->andThrow(new RuntimeException('File not found'));

    /** @var GitHubApiService $mockGitHubService */
    $collector = new ProjectContextCollector($mockGitHubService);

    // Set up semantic data with various standard library imports that should be filtered
    $bag = new ContextBag();
    $bag->semantics = [
        'src/Example.java' => [
            'language' => 'java',
            'imports' => [
                ['module' => 'java.util.List'],       // Java stdlib - should be filtered
                ['module' => 'javax.servlet.http'],   // Java stdlib - should be filtered
                ['module' => 'System.Collections'],   // .NET stdlib - should be filtered
                ['module' => 'os.path'],              // Python stdlib - should be filtered
                ['module' => 'GuzzleHttp\\Client'],   // External - should match
            ],
        ],
    ];

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    // Only guzzlehttp/guzzle should be prioritized (external package)
    // The standard library imports should be filtered out
    $depNames = array_column($bag->projectContext['dependencies'], 'name');
    $firstDep = $bag->projectContext['dependencies'][0];

    expect($firstDep['name'])->toBe('guzzlehttp/guzzle');
});
