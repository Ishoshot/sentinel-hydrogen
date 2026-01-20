<?php

declare(strict_types=1);

use App\Enums\ProviderType;
use App\Enums\Queue;
use App\Jobs\CodeIndexing\IndexCodeBatchJob;
use App\Models\CodeIndex;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\CodeIndexing\CodeIndexingService;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\Semantic\Contracts\SemanticAnalyzerInterface;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    Bus::fake([IndexCodeBatchJob::class]);

    $this->workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($this->workspace)->forProvider($provider)->create();
    $this->installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $this->repository = Repository::factory()->forInstallation($this->installation)->create([
        'workspace_id' => $this->workspace->id,
        'full_name' => 'owner/repo',
        'name' => 'repo',
    ]);

    $this->githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $this->semanticAnalyzer = Mockery::mock(SemanticAnalyzerInterface::class);

    app()->instance(GitHubApiServiceContract::class, $this->githubApi);
    app()->instance(SemanticAnalyzerInterface::class, $this->semanticAnalyzer);
});

describe('shouldIndexFile', function (): void {
    it('returns true for PHP files', function (): void {
        $service = app(CodeIndexingService::class);

        expect($service->shouldIndexFile('app/Models/User.php'))->toBeTrue()
            ->and($service->shouldIndexFile('tests/Feature/ExampleTest.php'))->toBeTrue();
    });

    it('returns true for JavaScript and TypeScript files', function (): void {
        $service = app(CodeIndexingService::class);

        expect($service->shouldIndexFile('resources/js/app.js'))->toBeTrue()
            ->and($service->shouldIndexFile('resources/js/app.ts'))->toBeTrue()
            ->and($service->shouldIndexFile('src/components/Button.tsx'))->toBeTrue()
            ->and($service->shouldIndexFile('src/components/Button.jsx'))->toBeTrue();
    });

    it('returns false for vendor files', function (): void {
        $service = app(CodeIndexingService::class);

        expect($service->shouldIndexFile('vendor/laravel/framework/src/Illuminate/Support/Str.php'))->toBeFalse();
    });

    it('returns false for node_modules files', function (): void {
        $service = app(CodeIndexingService::class);

        expect($service->shouldIndexFile('node_modules/lodash/index.js'))->toBeFalse();
    });

    it('returns false for non-indexable extensions', function (): void {
        $service = app(CodeIndexingService::class);

        expect($service->shouldIndexFile('public/images/logo.png'))->toBeFalse()
            ->and($service->shouldIndexFile('storage/logs/laravel.log'))->toBeFalse()
            ->and($service->shouldIndexFile('composer.lock'))->toBeFalse();
    });

    it('returns true for Vue and Svelte files', function (): void {
        $service = app(CodeIndexingService::class);

        expect($service->shouldIndexFile('resources/js/Pages/Dashboard.vue'))->toBeTrue()
            ->and($service->shouldIndexFile('src/App.svelte'))->toBeTrue();
    });
});

describe('indexFile', function (): void {
    it('creates code index for valid file', function (): void {
        $this->semanticAnalyzer->shouldReceive('analyzeFile')
            ->once()
            ->with('<?php class User {}', 'app/Models/User.php')
            ->andReturn([
                'classes' => [['name' => 'User', 'start_line' => 1, 'end_line' => 1]],
            ]);

        $service = app(CodeIndexingService::class);
        $result = $service->indexFile(
            $this->repository,
            'abc123',
            'app/Models/User.php',
            '<?php class User {}'
        );

        expect($result['indexed'])->toBeTrue()
            ->and($result['structure'])->toBeArray()
            ->and($result['structure']['classes'])->toHaveCount(1);

        $index = CodeIndex::where('repository_id', $this->repository->id)
            ->where('file_path', 'app/Models/User.php')
            ->first();

        expect($index)->not()->toBeNull()
            ->and($index->commit_sha)->toBe('abc123')
            ->and($index->file_type)->toBe('php')
            ->and($index->content)->toBe('<?php class User {}');
    });

    it('skips non-indexable files', function (): void {
        $service = app(CodeIndexingService::class);
        $result = $service->indexFile(
            $this->repository,
            'abc123',
            'vendor/autoload.php',
            '<?php // vendor file'
        );

        expect($result['indexed'])->toBeFalse()
            ->and($result['structure'])->toBeNull();

        expect(CodeIndex::where('repository_id', $this->repository->id)->count())->toBe(0);
    });

    it('updates existing index on re-indexing', function (): void {
        // Create initial index
        CodeIndex::factory()->create([
            'repository_id' => $this->repository->id,
            'file_path' => 'app/Models/User.php',
            'commit_sha' => 'old123',
            'content' => '<?php class User {}',
        ]);

        $this->semanticAnalyzer->shouldReceive('analyzeFile')
            ->once()
            ->andReturn(['classes' => []]);

        $service = app(CodeIndexingService::class);
        $service->indexFile(
            $this->repository,
            'new456',
            'app/Models/User.php',
            '<?php class User { public $name; }'
        );

        expect(CodeIndex::where('repository_id', $this->repository->id)->count())->toBe(1);

        $index = CodeIndex::where('repository_id', $this->repository->id)->first();
        expect($index->commit_sha)->toBe('new456')
            ->and($index->content)->toBe('<?php class User { public $name; }');
    });
});

describe('removeFiles', function (): void {
    it('removes specified files from index', function (): void {
        // Create some indexed files
        CodeIndex::factory()->create([
            'repository_id' => $this->repository->id,
            'file_path' => 'app/Models/User.php',
        ]);
        CodeIndex::factory()->create([
            'repository_id' => $this->repository->id,
            'file_path' => 'app/Models/Post.php',
        ]);
        CodeIndex::factory()->create([
            'repository_id' => $this->repository->id,
            'file_path' => 'app/Models/Comment.php',
        ]);

        $service = app(CodeIndexingService::class);
        $service->removeFiles($this->repository, ['app/Models/User.php', 'app/Models/Post.php']);

        expect(CodeIndex::where('repository_id', $this->repository->id)->count())->toBe(1);

        $remaining = CodeIndex::where('repository_id', $this->repository->id)->first();
        expect($remaining->file_path)->toBe('app/Models/Comment.php');
    });

    it('handles empty file list gracefully', function (): void {
        CodeIndex::factory()->create([
            'repository_id' => $this->repository->id,
            'file_path' => 'app/Models/User.php',
        ]);

        $service = app(CodeIndexingService::class);
        $service->removeFiles($this->repository, []);

        expect(CodeIndex::where('repository_id', $this->repository->id)->count())->toBe(1);
    });

    it('handles non-existent files gracefully', function (): void {
        CodeIndex::factory()->create([
            'repository_id' => $this->repository->id,
            'file_path' => 'app/Models/User.php',
        ]);

        $service = app(CodeIndexingService::class);
        $service->removeFiles($this->repository, ['app/Models/NonExistent.php']);

        expect(CodeIndex::where('repository_id', $this->repository->id)->count())->toBe(1);
    });
});

describe('indexChangedFiles', function (): void {
    it('dispatches jobs for added and modified files', function (): void {
        $service = app(CodeIndexingService::class);

        $changedFiles = [
            'added' => ['app/Models/NewModel.php', 'app/Services/NewService.php'],
            'modified' => ['app/Models/User.php'],
            'removed' => [],
        ];

        $service->indexChangedFiles($this->repository, 'commit123', $changedFiles);

        Bus::assertDispatched(IndexCodeBatchJob::class, function ($job) {
            return $job->repository->id === $this->repository->id
                && $job->commitSha === 'commit123';
        });
    });

    it('removes deleted files from index', function (): void {
        // Create indexed file that will be removed
        CodeIndex::factory()->create([
            'repository_id' => $this->repository->id,
            'file_path' => 'app/Models/OldModel.php',
        ]);

        $service = app(CodeIndexingService::class);

        $changedFiles = [
            'added' => [],
            'modified' => [],
            'removed' => ['app/Models/OldModel.php'],
        ];

        $service->indexChangedFiles($this->repository, 'commit123', $changedFiles);

        expect(CodeIndex::where('repository_id', $this->repository->id)
            ->where('file_path', 'app/Models/OldModel.php')
            ->exists())->toBeFalse();
    });

    it('skips non-indexable files', function (): void {
        $service = app(CodeIndexingService::class);

        $changedFiles = [
            'added' => ['vendor/package/file.php', 'node_modules/lib/index.js'],
            'modified' => ['public/images/logo.png'],
            'removed' => [],
        ];

        $service->indexChangedFiles($this->repository, 'commit123', $changedFiles);

        Bus::assertNotDispatched(IndexCodeBatchJob::class);
    });

    it('handles mixed indexable and non-indexable files', function (): void {
        $service = app(CodeIndexingService::class);

        $changedFiles = [
            'added' => ['app/Models/User.php', 'vendor/autoload.php'],
            'modified' => ['app/Services/UserService.php', 'node_modules/lodash/index.js'],
            'removed' => [],
        ];

        $service->indexChangedFiles($this->repository, 'commit123', $changedFiles);

        Bus::assertDispatched(IndexCodeBatchJob::class, function ($job) {
            // Should only include indexable files
            $filePaths = array_column($job->files, 'path');

            return count($filePaths) === 2
                && in_array('app/Models/User.php', $filePaths, true)
                && in_array('app/Services/UserService.php', $filePaths, true);
        });
    });

    it('falls back to full index for large change sets', function (): void {
        $this->githubApi->shouldReceive('getRepositoryTree')
            ->once()
            ->andReturn(['tree' => []]);

        $service = app(CodeIndexingService::class);

        // Create a large change set (>500 files)
        $largeChangeSet = [
            'added' => array_map(fn ($i) => "app/Generated/File{$i}.php", range(1, 600)),
            'modified' => [],
            'removed' => [],
        ];

        $service->indexChangedFiles($this->repository, 'commit123', $largeChangeSet);

        // When change set is too large, it should trigger full reindex
        // which calls getRepositoryTree
        // Mockery will verify getRepositoryTree was called
    });

    it('handles empty change set', function (): void {
        $service = app(CodeIndexingService::class);

        $changedFiles = [
            'added' => [],
            'modified' => [],
            'removed' => [],
        ];

        $service->indexChangedFiles($this->repository, 'commit123', $changedFiles);

        Bus::assertNotDispatched(IndexCodeBatchJob::class);
    });
});

describe('indexRepository', function (): void {
    it('dispatches batch jobs for indexable files', function (): void {
        $this->githubApi->shouldReceive('getRepositoryTree')
            ->once()
            ->with(12345, 'owner', 'repo', 'commit123', true)
            ->andReturn([
                'tree' => [
                    ['path' => 'app/Models/User.php', 'type' => 'blob', 'size' => 1000],
                    ['path' => 'app/Models/Post.php', 'type' => 'blob', 'size' => 2000],
                    ['path' => 'vendor/autoload.php', 'type' => 'blob', 'size' => 500],
                    ['path' => 'app/Models', 'type' => 'tree'], // Directory
                ],
            ]);

        $service = app(CodeIndexingService::class);
        $service->indexRepository($this->repository, 'commit123');

        Bus::assertDispatched(IndexCodeBatchJob::class, function ($job) {
            $filePaths = array_column($job->files, 'path');

            return count($filePaths) === 2
                && in_array('app/Models/User.php', $filePaths, true)
                && in_array('app/Models/Post.php', $filePaths, true)
                && ! in_array('vendor/autoload.php', $filePaths, true);
        });
    });

    it('dispatches jobs on correct queue', function (): void {
        $this->githubApi->shouldReceive('getRepositoryTree')
            ->once()
            ->andReturn([
                'tree' => [
                    ['path' => 'app/Models/User.php', 'type' => 'blob', 'size' => 1000],
                ],
            ]);

        $service = app(CodeIndexingService::class);
        $service->indexRepository($this->repository, 'commit123');

        Bus::assertDispatched(IndexCodeBatchJob::class, function ($job) {
            return $job->queue === Queue::CodeIndexing->value;
        });
    });

    it('handles repositories with null installation relation', function (): void {
        // Create a repository with an installation, then manually set the relation to null
        $repoWithInstallation = Repository::factory()->forInstallation($this->installation)->create([
            'workspace_id' => $this->workspace->id,
        ]);

        // Unset the installation relation to simulate detached state
        $repoWithInstallation->setRelation('installation', null);

        $service = app(CodeIndexingService::class);
        $service->indexRepository($repoWithInstallation, 'commit123');

        Bus::assertNotDispatched(IndexCodeBatchJob::class);
    });
});
