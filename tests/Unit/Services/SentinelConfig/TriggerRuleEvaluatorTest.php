<?php

declare(strict_types=1);

use App\DataTransferObjects\SentinelConfig\TriggersConfig;
use App\Services\SentinelConfig\TriggerRuleEvaluator;

describe('TriggerRuleEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new TriggerRuleEvaluator();
    });

    describe('target branch matching', function (): void {
        it('allows PR when base branch matches target branches', function (): void {
            $config = new TriggersConfig(targetBranches: ['main', 'master']);
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'main',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue()
                ->and($result['reason'])->toBeNull();
        });

        it('blocks PR when base branch does not match target branches', function (): void {
            $config = new TriggersConfig(targetBranches: ['main', 'master']);
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'develop',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeFalse()
                ->and($result['reason'])->toContain('develop')
                ->and($result['reason'])->toContain('does not match');
        });

        it('supports glob patterns for target branches', function (): void {
            $config = new TriggersConfig(targetBranches: ['release/*', 'main']);
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'release/v1.0',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });

        it('allows all target branches when list is empty', function (): void {
            $config = new TriggersConfig(targetBranches: []);
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'any-branch',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });
    });

    describe('skip source branches', function (): void {
        it('blocks PR when source branch matches skip pattern', function (): void {
            $config = new TriggersConfig(
                targetBranches: ['main'],
                skipSourceBranches: ['dependabot/*', 'renovate/*']
            );
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'main',
                'head_branch' => 'dependabot/npm-security-fix',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeFalse()
                ->and($result['reason'])->toContain('dependabot/npm-security-fix')
                ->and($result['reason'])->toContain('skip pattern');
        });

        it('allows PR when source branch does not match skip patterns', function (): void {
            $config = new TriggersConfig(
                targetBranches: ['main'],
                skipSourceBranches: ['dependabot/*']
            );
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'main',
                'head_branch' => 'feature/my-feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });

        it('allows all source branches when skip list is empty', function (): void {
            $config = new TriggersConfig(
                targetBranches: ['main'],
                skipSourceBranches: []
            );
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'main',
                'head_branch' => 'any-branch-name',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });
    });

    describe('skip labels', function (): void {
        it('blocks PR when it has a skip label', function (): void {
            $config = new TriggersConfig(
                targetBranches: ['main'],
                skipLabels: ['no-review', 'wip']
            );
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'main',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => ['no-review', 'bug'],
            ]);

            expect($result['should_trigger'])->toBeFalse()
                ->and($result['reason'])->toContain('no-review');
        });

        it('allows PR when it does not have skip labels', function (): void {
            $config = new TriggersConfig(
                targetBranches: ['main'],
                skipLabels: ['no-review']
            );
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'main',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => ['bug', 'enhancement'],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });

        it('allows PR when no labels and skip labels configured', function (): void {
            $config = new TriggersConfig(
                targetBranches: ['main'],
                skipLabels: ['no-review']
            );
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'main',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });
    });

    describe('skip authors', function (): void {
        it('blocks PR from skip author', function (): void {
            $config = new TriggersConfig(
                targetBranches: ['main'],
                skipAuthors: ['dependabot[bot]', 'renovate[bot]']
            );
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'main',
                'head_branch' => 'feature',
                'author_login' => 'dependabot[bot]',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeFalse()
                ->and($result['reason'])->toContain('dependabot[bot]')
                ->and($result['reason'])->toContain('skip list');
        });

        it('allows PR from non-skip author', function (): void {
            $config = new TriggersConfig(
                targetBranches: ['main'],
                skipAuthors: ['dependabot[bot]']
            );
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'main',
                'head_branch' => 'feature',
                'author_login' => 'developer',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });
    });

    describe('glob pattern matching', function (): void {
        it('matches wildcard at end of pattern', function (): void {
            $config = new TriggersConfig(targetBranches: ['release/*']);
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'release/v1.2.3',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });

        it('matches wildcard in middle of pattern', function (): void {
            $config = new TriggersConfig(targetBranches: ['feature/*/hotfix']);
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'feature/team-a/hotfix',
                'head_branch' => 'fix',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });

        it('does not match when pattern does not fit', function (): void {
            $config = new TriggersConfig(targetBranches: ['release/*']);
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'develop',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeFalse();
        });

        it('matches single character wildcard', function (): void {
            $config = new TriggersConfig(targetBranches: ['v?.0']);
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'v1.0',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });
    });

    describe('default config', function (): void {
        it('uses default triggers correctly', function (): void {
            $config = TriggersConfig::default();
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'main',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeTrue();
        });

        it('blocks PR to non-default branch with default config', function (): void {
            $config = TriggersConfig::default();
            $result = $this->evaluator->evaluate($config, [
                'base_branch' => 'develop',
                'head_branch' => 'feature',
                'author_login' => 'testuser',
                'labels' => [],
            ]);

            expect($result['should_trigger'])->toBeFalse();
        });
    });
});
