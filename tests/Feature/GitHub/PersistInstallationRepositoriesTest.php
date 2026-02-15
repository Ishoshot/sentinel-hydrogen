<?php

declare(strict_types=1);

use App\Actions\GitHub\PersistInstallationRepositories;
use App\Models\Installation;
use App\Models\Repository;

beforeEach(function (): void {
    $this->action = app(PersistInstallationRepositories::class);
});

describe('syncFromGitHub', function (): void {
    it('creates new repositories and returns added count', function (): void {
        $installation = Installation::factory()->create();

        $githubRepos = [
            [
                'id' => 100001,
                'name' => 'repo-one',
                'full_name' => 'org/repo-one',
                'private' => false,
                'default_branch' => 'main',
                'language' => 'PHP',
                'description' => 'First repo',
            ],
            [
                'id' => 100002,
                'name' => 'repo-two',
                'full_name' => 'org/repo-two',
                'private' => true,
                'default_branch' => 'develop',
                'language' => 'TypeScript',
                'description' => null,
            ],
        ];

        $result = $this->action->syncFromGitHub($installation, $githubRepos);

        expect($result['added'])->toBe(2)
            ->and($result['updated'])->toBe(0)
            ->and($result['removed'])->toBe(0)
            ->and($result['synced_repository_ids'])->toHaveCount(2)
            ->and($result['newly_created_repository_ids'])->toHaveCount(2);

        $this->assertDatabaseHas('repositories', [
            'installation_id' => $installation->id,
            'github_id' => 100001,
            'name' => 'repo-one',
            'full_name' => 'org/repo-one',
            'private' => false,
            'default_branch' => 'main',
            'language' => 'PHP',
            'description' => 'First repo',
        ]);

        $this->assertDatabaseHas('repositories', [
            'installation_id' => $installation->id,
            'github_id' => 100002,
            'name' => 'repo-two',
            'full_name' => 'org/repo-two',
            'private' => true,
            'default_branch' => 'develop',
            'language' => 'TypeScript',
        ]);
    });

    it('updates existing repositories and returns updated count', function (): void {
        $installation = Installation::factory()->create();

        Repository::factory()->forInstallation($installation)->create([
            'github_id' => 200001,
            'name' => 'old-name',
            'full_name' => 'org/old-name',
            'private' => false,
        ]);

        $githubRepos = [
            [
                'id' => 200001,
                'name' => 'new-name',
                'full_name' => 'org/new-name',
                'private' => true,
                'default_branch' => 'main',
                'language' => 'Go',
                'description' => 'Updated description',
            ],
        ];

        $result = $this->action->syncFromGitHub($installation, $githubRepos);

        expect($result['added'])->toBe(0)
            ->and($result['updated'])->toBe(1)
            ->and($result['removed'])->toBe(0)
            ->and($result['newly_created_repository_ids'])->toBeEmpty();

        $this->assertDatabaseHas('repositories', [
            'installation_id' => $installation->id,
            'github_id' => 200001,
            'name' => 'new-name',
            'full_name' => 'org/new-name',
            'private' => true,
        ]);
    });

    it('removes repositories no longer present in github', function (): void {
        $installation = Installation::factory()->create();

        Repository::factory()->forInstallation($installation)->create([
            'github_id' => 300001,
            'name' => 'keep-repo',
            'full_name' => 'org/keep-repo',
        ]);

        Repository::factory()->forInstallation($installation)->create([
            'github_id' => 300002,
            'name' => 'remove-repo',
            'full_name' => 'org/remove-repo',
        ]);

        $githubRepos = [
            [
                'id' => 300001,
                'name' => 'keep-repo',
                'full_name' => 'org/keep-repo',
                'private' => false,
                'default_branch' => 'main',
            ],
        ];

        $result = $this->action->syncFromGitHub($installation, $githubRepos);

        expect($result['removed'])->toBe(1)
            ->and($result['updated'])->toBe(1);

        $this->assertDatabaseMissing('repositories', [
            'github_id' => 300002,
        ]);

        $this->assertDatabaseHas('repositories', [
            'github_id' => 300001,
        ]);
    });

    it('creates default repository settings for new repositories', function (): void {
        $installation = Installation::factory()->create();

        $githubRepos = [
            [
                'id' => 400001,
                'name' => 'settings-repo',
                'full_name' => 'org/settings-repo',
                'private' => false,
                'default_branch' => 'main',
            ],
        ];

        $this->action->syncFromGitHub($installation, $githubRepos);

        $repository = Repository::query()
            ->where('github_id', 400001)
            ->first();

        $this->assertDatabaseHas('repository_settings', [
            'repository_id' => $repository->id,
            'workspace_id' => $installation->workspace_id,
            'auto_review_enabled' => true,
        ]);
    });

    it('handles sync with empty repositories array', function (): void {
        $installation = Installation::factory()->create();

        Repository::factory()->forInstallation($installation)->create([
            'github_id' => 500001,
        ]);

        $result = $this->action->syncFromGitHub($installation, []);

        expect($result['added'])->toBe(0)
            ->and($result['updated'])->toBe(0)
            ->and($result['removed'])->toBe(1)
            ->and($result['synced_repository_ids'])->toBeEmpty()
            ->and($result['newly_created_repository_ids'])->toBeEmpty();
    });

    it('uses default branch main when not provided', function (): void {
        $installation = Installation::factory()->create();

        $githubRepos = [
            [
                'id' => 600001,
                'name' => 'no-branch-repo',
                'full_name' => 'org/no-branch-repo',
                'private' => false,
            ],
        ];

        $this->action->syncFromGitHub($installation, $githubRepos);

        $this->assertDatabaseHas('repositories', [
            'github_id' => 600001,
            'default_branch' => 'main',
        ]);
    });
});

describe('addFromWebhook', function (): void {
    it('creates new repositories from webhook data', function (): void {
        $installation = Installation::factory()->create();

        $repositories = [
            [
                'id' => 700001,
                'name' => 'webhook-repo',
                'full_name' => 'org/webhook-repo',
                'private' => false,
            ],
        ];

        $result = $this->action->addFromWebhook($installation, $repositories);

        expect($result['added'])->toBe(1)
            ->and($result['added_repository_ids'])->toHaveCount(1);

        $this->assertDatabaseHas('repositories', [
            'installation_id' => $installation->id,
            'github_id' => 700001,
            'name' => 'webhook-repo',
            'full_name' => 'org/webhook-repo',
            'private' => false,
            'default_branch' => 'main',
        ]);
    });

    it('does not create duplicate repositories', function (): void {
        $installation = Installation::factory()->create();

        Repository::factory()->forInstallation($installation)->create([
            'github_id' => 800001,
            'name' => 'existing-repo',
            'full_name' => 'org/existing-repo',
        ]);

        $repositories = [
            [
                'id' => 800001,
                'name' => 'existing-repo',
                'full_name' => 'org/existing-repo',
                'private' => false,
            ],
        ];

        $result = $this->action->addFromWebhook($installation, $repositories);

        expect($result['added'])->toBe(0)
            ->and($result['added_repository_ids'])->toBeEmpty();

        $this->assertDatabaseCount('repositories', 1);
    });

    it('creates default settings for new webhook repositories', function (): void {
        $installation = Installation::factory()->create();

        $repositories = [
            [
                'id' => 900001,
                'name' => 'webhook-settings-repo',
                'full_name' => 'org/webhook-settings-repo',
                'private' => true,
            ],
        ];

        $this->action->addFromWebhook($installation, $repositories);

        $repository = Repository::query()->where('github_id', 900001)->first();

        $this->assertDatabaseHas('repository_settings', [
            'repository_id' => $repository->id,
            'workspace_id' => $installation->workspace_id,
            'auto_review_enabled' => true,
        ]);
    });

    it('handles multiple repositories in a single webhook', function (): void {
        $installation = Installation::factory()->create();

        $repositories = [
            [
                'id' => 1000001,
                'name' => 'multi-repo-one',
                'full_name' => 'org/multi-repo-one',
                'private' => false,
            ],
            [
                'id' => 1000002,
                'name' => 'multi-repo-two',
                'full_name' => 'org/multi-repo-two',
                'private' => true,
            ],
        ];

        $result = $this->action->addFromWebhook($installation, $repositories);

        expect($result['added'])->toBe(2)
            ->and($result['added_repository_ids'])->toHaveCount(2);
    });
});

describe('removeFromWebhook', function (): void {
    it('removes repositories matching webhook data', function (): void {
        $installation = Installation::factory()->create();

        Repository::factory()->forInstallation($installation)->create([
            'github_id' => 1100001,
            'name' => 'to-remove',
            'full_name' => 'org/to-remove',
        ]);

        Repository::factory()->forInstallation($installation)->create([
            'github_id' => 1100002,
            'name' => 'to-keep',
            'full_name' => 'org/to-keep',
        ]);

        $repositories = [
            [
                'id' => 1100001,
                'name' => 'to-remove',
                'full_name' => 'org/to-remove',
            ],
        ];

        $removed = $this->action->removeFromWebhook($installation, $repositories);

        expect($removed)->toBe(1);

        $this->assertDatabaseMissing('repositories', [
            'github_id' => 1100001,
        ]);

        $this->assertDatabaseHas('repositories', [
            'github_id' => 1100002,
        ]);
    });

    it('returns zero when no repositories match', function (): void {
        $installation = Installation::factory()->create();

        $repositories = [
            [
                'id' => 1200001,
                'name' => 'nonexistent',
                'full_name' => 'org/nonexistent',
            ],
        ];

        $removed = $this->action->removeFromWebhook($installation, $repositories);

        expect($removed)->toBe(0);
    });

    it('only removes repositories belonging to the given installation', function (): void {
        $installation1 = Installation::factory()->create();
        $installation2 = Installation::factory()->create();

        Repository::factory()->forInstallation($installation1)->create([
            'github_id' => 1300001,
            'name' => 'shared-name',
            'full_name' => 'org1/shared-name',
        ]);

        Repository::factory()->forInstallation($installation2)->create([
            'github_id' => 1300001,
            'name' => 'shared-name',
            'full_name' => 'org2/shared-name',
        ]);

        $repositories = [
            [
                'id' => 1300001,
                'name' => 'shared-name',
                'full_name' => 'org1/shared-name',
            ],
        ];

        $removed = $this->action->removeFromWebhook($installation1, $repositories);

        expect($removed)->toBe(1);

        $this->assertDatabaseMissing('repositories', [
            'installation_id' => $installation1->id,
            'github_id' => 1300001,
        ]);

        $this->assertDatabaseHas('repositories', [
            'installation_id' => $installation2->id,
            'github_id' => 1300001,
        ]);
    });
});
