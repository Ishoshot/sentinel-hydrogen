<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\Context\Collectors\SemanticCollector;
use App\Services\Context\ContextBag;
use App\Services\Semantic\Contracts\SemanticAnalyzerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    $this->repository = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $this->run = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
    ]);
});

it('has correct name and priority', function (): void {
    $service = Mockery::mock(SemanticAnalyzerInterface::class);
    $collector = new SemanticCollector($service);

    expect($collector->name())->toBe('semantic')
        ->and($collector->priority())->toBe(80);
});

it('should collect when repository and run are present', function (): void {
    $service = Mockery::mock(SemanticAnalyzerInterface::class);
    $collector = new SemanticCollector($service);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    expect($shouldCollect)->toBeTrue();
});

it('should not collect without repository or run', function (): void {
    $service = Mockery::mock(SemanticAnalyzerInterface::class);
    $collector = new SemanticCollector($service);

    expect($collector->shouldCollect([]))->toBeFalse()
        ->and($collector->shouldCollect(['repository' => $this->repository]))->toBeFalse()
        ->and($collector->shouldCollect(['run' => $this->run]))->toBeFalse();
});

it('does not collect when no file contents are available', function (): void {
    $service = Mockery::mock(SemanticAnalyzerInterface::class);
    $service->shouldReceive('analyzeFiles')->never();

    $collector = new SemanticCollector($service);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    expect($bag->semantics)->toBeEmpty();
});

it('collects semantic data from file contents', function (): void {
    $fileContents = [
        'src/UserService.php' => '<?php function getUser() {}',
        'src/UserController.php' => '<?php class UserController {}',
    ];

    $expectedSemantics = [
        'src/UserService.php' => [
            'language' => 'php',
            'functions' => [
                [
                    'name' => 'getUser',
                    'line_start' => 1,
                    'line_end' => 1,
                    'parameters' => [],
                ],
            ],
            'classes' => [],
            'imports' => [],
            'calls' => [],
            'errors' => [],
        ],
        'src/UserController.php' => [
            'language' => 'php',
            'functions' => [],
            'classes' => [
                [
                    'name' => 'UserController',
                    'line_start' => 1,
                    'line_end' => 1,
                ],
            ],
            'imports' => [],
            'calls' => [],
            'errors' => [],
        ],
    ];

    $service = Mockery::mock(SemanticAnalyzerInterface::class);
    $service->shouldReceive('analyzeFiles')
        ->once()
        ->with($fileContents)
        ->andReturn($expectedSemantics);

    $collector = new SemanticCollector($service);
    $bag = new ContextBag(fileContents: $fileContents);

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    expect($bag->semantics)->toBe($expectedSemantics);
});

it('limits the number of files analyzed', function (): void {
    // Create 20 files
    $fileContents = [];
    for ($i = 1; $i <= 20; $i++) {
        $fileContents["file{$i}.php"] = "<?php function test{$i}() {}";
    }

    $service = Mockery::mock(SemanticAnalyzerInterface::class);
    $service->shouldReceive('analyzeFiles')
        ->once()
        ->with(Mockery::on(function ($files) {
            return count($files) === 15; // MAX_FILES constant
        }))
        ->andReturn([]);

    $collector = new SemanticCollector($service);
    $bag = new ContextBag(fileContents: $fileContents);

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);
});

it('skips files that are too large', function (): void {
    $largeContent = str_repeat('<?php echo "test";', 10000); // > 100KB
    $smallContent = '<?php function test() {}';

    $fileContents = [
        'large.php' => $largeContent,
        'small.php' => $smallContent,
    ];

    $service = Mockery::mock(SemanticAnalyzerInterface::class);
    $service->shouldReceive('analyzeFiles')
        ->once()
        ->with(Mockery::on(function ($files) {
            return count($files) === 1 && isset($files['small.php']);
        }))
        ->andReturn([]);

    $collector = new SemanticCollector($service);
    $bag = new ContextBag(fileContents: $fileContents);

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);
});

it('applies adaptive semantic limits by workspace tier', function (): void {
    config([
        'reviews.adaptive_limits.enabled' => true,
        'reviews.adaptive_limits.pr_size_buckets' => [
            'small' => ['max_files_changed' => 1, 'max_lines_changed' => 20],
            'medium' => ['max_files_changed' => 10, 'max_lines_changed' => 500],
            'xlarge' => ['max_files_changed' => 1000000, 'max_lines_changed' => 1000000],
        ],
        'reviews.adaptive_limits.tiers.foundation.medium.semantic.max_files' => 2,
        'reviews.adaptive_limits.tiers.foundation.medium.semantic.max_file_size' => 50000,
    ]);

    $fileContents = [
        'file1.php' => '<?php function one() {}',
        'file2.php' => '<?php function two() {}',
        'file3.php' => '<?php function three() {}',
    ];

    $service = Mockery::mock(SemanticAnalyzerInterface::class);
    $service->shouldReceive('analyzeFiles')
        ->once()
        ->with(Mockery::on(function (array $files): bool {
            return count($files) === 2
                && array_key_exists('file1.php', $files)
                && array_key_exists('file2.php', $files);
        }))
        ->andReturn([]);

    $collector = new SemanticCollector($service);
    $bag = new ContextBag(
        files: [
            ['filename' => 'file1.php', 'status' => 'modified', 'additions' => 20, 'deletions' => 5, 'changes' => 25, 'patch' => '+a'],
            ['filename' => 'file2.php', 'status' => 'modified', 'additions' => 18, 'deletions' => 3, 'changes' => 21, 'patch' => '+b'],
            ['filename' => 'file3.php', 'status' => 'modified', 'additions' => 16, 'deletions' => 2, 'changes' => 18, 'patch' => '+c'],
        ],
        fileContents: $fileContents,
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);
});
