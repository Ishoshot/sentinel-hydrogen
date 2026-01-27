<?php

declare(strict_types=1);

use App\Enums\ProviderType;
use App\Jobs\GitHub\ProcessPushWebhook;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;

beforeEach(function (): void {
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
        'default_branch' => 'main',
    ]);

    $this->indexingService = Mockery::mock(CodeIndexingServiceContract::class);
    app()->instance(CodeIndexingServiceContract::class, $this->indexingService);
});

describe('incremental indexing', function (): void {
    it('triggers incremental indexing for pushes to default branch', function (): void {
        $this->indexingService->shouldReceive('indexChangedFiles')
            ->once()
            ->withArgs(function ($repository, $commitSha, $changedFiles) {
                return $repository->id === $this->repository->id
                    && $commitSha === 'abc123456'
                    && $changedFiles['added'] === ['app/Models/NewModel.php']
                    && $changedFiles['modified'] === ['app/Services/UserService.php']
                    && $changedFiles['removed'] === ['app/Helpers/OldHelper.php'];
            });

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'abc123456',
            'installation' => ['id' => 12345],
            'repository' => [
                'id' => $this->repository->github_id,
                'full_name' => 'owner/repo',
            ],
            'commits' => [
                [
                    'added' => ['app/Models/NewModel.php'],
                    'modified' => ['app/Services/UserService.php'],
                    'removed' => ['app/Helpers/OldHelper.php'],
                ],
            ],
        ];

        ProcessPushWebhook::dispatchSync($payload);
    });

    it('skips indexing for pushes to non-default branches', function (): void {
        $this->indexingService->shouldNotReceive('indexChangedFiles');

        $payload = [
            'ref' => 'refs/heads/feature/new-feature',
            'after' => 'abc123456',
            'installation' => ['id' => 12345],
            'repository' => [
                'id' => $this->repository->github_id,
                'full_name' => 'owner/repo',
            ],
            'commits' => [
                [
                    'added' => ['app/Models/NewModel.php'],
                    'modified' => [],
                    'removed' => [],
                ],
            ],
        ];

        ProcessPushWebhook::dispatchSync($payload);
    });

    it('aggregates changes from multiple commits', function (): void {
        $this->indexingService->shouldReceive('indexChangedFiles')
            ->once()
            ->withArgs(function ($repository, $commitSha, $changedFiles) {
                // Should contain unique files from all commits
                return count($changedFiles['added']) === 2
                    && in_array('app/Models/User.php', $changedFiles['added'], true)
                    && in_array('app/Models/Post.php', $changedFiles['added'], true)
                    && count($changedFiles['modified']) === 1
                    && in_array('app/Services/UserService.php', $changedFiles['modified'], true);
            });

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'commit3',
            'installation' => ['id' => 12345],
            'repository' => [
                'id' => $this->repository->github_id,
                'full_name' => 'owner/repo',
            ],
            'commits' => [
                [
                    'added' => ['app/Models/User.php'],
                    'modified' => [],
                    'removed' => [],
                ],
                [
                    'added' => ['app/Models/Post.php'],
                    'modified' => ['app/Services/UserService.php'],
                    'removed' => [],
                ],
            ],
        ];

        ProcessPushWebhook::dispatchSync($payload);
    });

    it('deduplicates files modified in multiple commits', function (): void {
        $this->indexingService->shouldReceive('indexChangedFiles')
            ->once()
            ->withArgs(function ($repository, $commitSha, $changedFiles) {
                // Same file modified in multiple commits should appear once
                return count($changedFiles['modified']) === 1
                    && in_array('app/Services/UserService.php', $changedFiles['modified'], true);
            });

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'commit2',
            'installation' => ['id' => 12345],
            'repository' => [
                'id' => $this->repository->github_id,
                'full_name' => 'owner/repo',
            ],
            'commits' => [
                [
                    'added' => [],
                    'modified' => ['app/Services/UserService.php'],
                    'removed' => [],
                ],
                [
                    'added' => [],
                    'modified' => ['app/Services/UserService.php'], // Same file again
                    'removed' => [],
                ],
            ],
        ];

        ProcessPushWebhook::dispatchSync($payload);
    });

    it('skips indexing when no files changed', function (): void {
        $this->indexingService->shouldNotReceive('indexChangedFiles');

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'abc123456',
            'installation' => ['id' => 12345],
            'repository' => [
                'id' => $this->repository->github_id,
                'full_name' => 'owner/repo',
            ],
            'commits' => [
                [
                    'added' => [],
                    'modified' => [],
                    'removed' => [],
                ],
            ],
        ];

        ProcessPushWebhook::dispatchSync($payload);
    });

    it('skips indexing when commits array is empty', function (): void {
        $this->indexingService->shouldNotReceive('indexChangedFiles');

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'abc123456',
            'installation' => ['id' => 12345],
            'repository' => [
                'id' => $this->repository->github_id,
                'full_name' => 'owner/repo',
            ],
            'commits' => [],
        ];

        ProcessPushWebhook::dispatchSync($payload);
    });

    it('handles missing after SHA gracefully', function (): void {
        $this->indexingService->shouldNotReceive('indexChangedFiles');

        $payload = [
            'ref' => 'refs/heads/main',
            // 'after' is missing
            'installation' => ['id' => 12345],
            'repository' => [
                'id' => $this->repository->github_id,
                'full_name' => 'owner/repo',
            ],
            'commits' => [
                [
                    'added' => ['app/Models/User.php'],
                    'modified' => [],
                    'removed' => [],
                ],
            ],
        ];

        ProcessPushWebhook::dispatchSync($payload);
    });
});

describe('repository lookup', function (): void {
    it('skips processing for unknown installation', function (): void {
        $this->indexingService->shouldNotReceive('indexChangedFiles');

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'abc123456',
            'installation' => ['id' => 99999], // Unknown installation
            'repository' => [
                'id' => 123456,
                'full_name' => 'other/repo',
            ],
            'commits' => [
                ['added' => ['file.php'], 'modified' => [], 'removed' => []],
            ],
        ];

        ProcessPushWebhook::dispatchSync($payload);
    });

    it('skips processing for unknown repository', function (): void {
        $this->indexingService->shouldNotReceive('indexChangedFiles');

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'abc123456',
            'installation' => ['id' => 12345],
            'repository' => [
                'id' => 99999999, // Unknown repository
                'full_name' => 'owner/unknown',
            ],
            'commits' => [
                ['added' => ['file.php'], 'modified' => [], 'removed' => []],
            ],
        ];

        ProcessPushWebhook::dispatchSync($payload);
    });
});
