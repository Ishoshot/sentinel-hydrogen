<?php

declare(strict_types=1);

use App\Enums\Queue;
use App\Jobs\Reviews\ExecuteReviewRun;
use App\Jobs\Reviews\PostRunAnnotations;
use App\Services\Queue\JobContext;
use App\Services\Queue\QueueResolver;
use App\Services\Queue\QueueRuleResult;
use App\Services\Queue\Rules\AnnotationJobRule;
use App\Services\Queue\Rules\LongRunningJobRule;
use App\Services\Queue\Rules\ReviewJobTierRule;
use App\Services\Queue\Rules\SystemJobRule;
use App\Services\Queue\Rules\WebhookJobRule;

describe('Queue Enum', function (): void {
    it('has correct priority ordering', function (): void {
        expect(Queue::System->priority())->toBeLessThan(Queue::Webhooks->priority());
        expect(Queue::Webhooks->priority())->toBeLessThan(Queue::ReviewsEnterprise->priority());
        expect(Queue::ReviewsEnterprise->priority())->toBeLessThan(Queue::ReviewsPaid->priority());
        expect(Queue::ReviewsPaid->priority())->toBeLessThan(Queue::ReviewsDefault->priority());
        expect(Queue::ReviewsDefault->priority())->toBeLessThan(Queue::Default->priority());
        expect(Queue::Default->priority())->toBeLessThan(Queue::LongRunning->priority());
    });

    it('returns queues sorted by priority', function (): void {
        $sorted = Queue::byPriority();

        expect($sorted[0])->toBe(Queue::System);
        expect($sorted[1])->toBe(Queue::Webhooks);
    });

    it('returns correct review queue for tier', function (): void {
        expect(Queue::reviewQueueForTier('enterprise'))->toBe(Queue::ReviewsEnterprise);
        expect(Queue::reviewQueueForTier('paid'))->toBe(Queue::ReviewsPaid);
        expect(Queue::reviewQueueForTier('pro'))->toBe(Queue::ReviewsPaid);
        expect(Queue::reviewQueueForTier('team'))->toBe(Queue::ReviewsPaid);
        expect(Queue::reviewQueueForTier('free'))->toBe(Queue::ReviewsDefault);
        expect(Queue::reviewQueueForTier('unknown'))->toBe(Queue::ReviewsDefault);
    });

    it('identifies review queues correctly', function (): void {
        expect(Queue::ReviewsEnterprise->isReviewQueue())->toBeTrue();
        expect(Queue::ReviewsPaid->isReviewQueue())->toBeTrue();
        expect(Queue::ReviewsDefault->isReviewQueue())->toBeTrue();
        expect(Queue::Default->isReviewQueue())->toBeFalse();
        expect(Queue::System->isReviewQueue())->toBeFalse();
    });

    it('identifies high priority queues correctly', function (): void {
        expect(Queue::System->isHighPriority())->toBeTrue();
        expect(Queue::Webhooks->isHighPriority())->toBeTrue();
        expect(Queue::ReviewsEnterprise->isHighPriority())->toBeFalse();
        expect(Queue::Default->isHighPriority())->toBeFalse();
    });
});

describe('JobContext', function (): void {
    it('creates context for workspace with tier', function (): void {
        $workspace = App\Models\Workspace::factory()->make(['id' => 1]);

        $context = JobContext::forWorkspace(
            ExecuteReviewRun::class,
            $workspace,
            isUserInitiated: true,
            importance: 'high'
        );

        expect($context->jobClass)->toBe(ExecuteReviewRun::class);
        expect($context->workspaceId)->toBe(1);
        expect($context->isUserInitiated)->toBeTrue();
        expect($context->importance)->toBe('high');
        expect($context->isSystemJob)->toBeFalse();
    });

    it('creates context for system job', function (): void {
        $context = JobContext::forSystemJob(
            'App\Jobs\System\CleanupJob',
            importance: 'critical'
        );

        expect($context->isSystemJob)->toBeTrue();
        expect($context->importance)->toBe('critical');
        expect($context->isCritical())->toBeTrue();
    });

    it('creates context for webhook job', function (): void {
        $context = JobContext::forWebhook(
            'App\Jobs\ProcessWebhook',
            workspaceId: 5
        );

        expect($context->getMeta('source'))->toBe('webhook');
        expect($context->workspaceId)->toBe(5);
        expect($context->importance)->toBe('high');
    });

    it('detects long running jobs', function (): void {
        $shortJob = new JobContext(
            jobClass: 'App\Jobs\QuickJob',
            estimatedDurationSeconds: 60
        );

        $longJob = new JobContext(
            jobClass: 'App\Jobs\LongJob',
            estimatedDurationSeconds: 180
        );

        expect($shortJob->isLongRunning())->toBeFalse();
        expect($longJob->isLongRunning())->toBeTrue();
    });

    it('detects paid tiers correctly', function (): void {
        $foundation = new JobContext(jobClass: 'App\Jobs\Test', tier: 'foundation');
        $illuminate = new JobContext(jobClass: 'App\Jobs\Test', tier: 'illuminate');
        $sanctum = new JobContext(jobClass: 'App\Jobs\Test', tier: 'sanctum');

        expect($foundation->isPaidTier())->toBeFalse();
        expect($illuminate->isPaidTier())->toBeTrue();
        expect($sanctum->isPaidTier())->toBeTrue();
        expect($sanctum->isEnterpriseTier())->toBeTrue();
        expect($illuminate->isEnterpriseTier())->toBeFalse();
    });
});

describe('QueueRuleResult', function (): void {
    it('creates force result', function (): void {
        $result = QueueRuleResult::force(Queue::System, 'Test reason');

        expect($result->isForced())->toBeTrue();
        expect($result->forcedQueue)->toBe(Queue::System);
        expect($result->reason)->toBe('Test reason');
        expect($result->hasEffect())->toBeTrue();
    });

    it('creates boost result', function (): void {
        $result = QueueRuleResult::boost(Queue::ReviewsPaid, 50, 'Boosted');

        expect($result->isForced())->toBeFalse();
        expect($result->scoreAdjustment)->toBe(50);
        expect($result->targetQueue)->toBe(Queue::ReviewsPaid);
        expect($result->hasEffect())->toBeTrue();
    });

    it('creates penalize result', function (): void {
        $result = QueueRuleResult::penalize(Queue::Bulk, 30, 'Penalized');

        expect($result->isForced())->toBeFalse();
        expect($result->scoreAdjustment)->toBe(-30);
        expect($result->targetQueue)->toBe(Queue::Bulk);
    });

    it('creates skip result', function (): void {
        $result = QueueRuleResult::skip('No action needed');

        expect($result->isForced())->toBeFalse();
        expect($result->hasEffect())->toBeFalse();
        expect($result->scoreAdjustment)->toBe(0);
    });
});

describe('QueueResolver', function (): void {
    it('routes system jobs to system queue', function (): void {
        $resolver = new QueueResolver([new SystemJobRule()]);
        $context = JobContext::forSystemJob('App\Jobs\SystemJob');

        $resolution = $resolver->resolve($context);

        expect($resolution->queue)->toBe(Queue::System);
        expect($resolution->wasForced())->toBeTrue();
        expect($resolution->forcedBy)->toBe('system_job');
    });

    it('routes webhook jobs to webhooks queue', function (): void {
        $resolver = new QueueResolver([new WebhookJobRule()]);
        $context = JobContext::forWebhook('App\Jobs\ProcessWebhook');

        $resolution = $resolver->resolve($context);

        expect($resolution->queue)->toBe(Queue::Webhooks);
        expect($resolution->forcedBy)->toBe('webhook_job');
    });

    it('routes review jobs based on tier', function (): void {
        $resolver = new QueueResolver([new ReviewJobTierRule()]);

        // Sanctum tier (with priority queue enabled)
        $sanctumContext = new JobContext(
            jobClass: ExecuteReviewRun::class,
            tier: 'sanctum',
            metadata: ['priority_queue' => true],
        );
        expect($resolver->resolve($sanctumContext)->queue)->toBe(Queue::ReviewsEnterprise);

        // Illuminate tier (with priority queue enabled)
        $illuminateContext = new JobContext(
            jobClass: ExecuteReviewRun::class,
            tier: 'illuminate',
            metadata: ['priority_queue' => true],
        );
        expect($resolver->resolve($illuminateContext)->queue)->toBe(Queue::ReviewsPaid);

        // Foundation tier
        $foundationContext = new JobContext(
            jobClass: ExecuteReviewRun::class,
            tier: 'foundation'
        );
        expect($resolver->resolve($foundationContext)->queue)->toBe(Queue::ReviewsDefault);
    });

    it('routes annotation jobs to annotations queue', function (): void {
        $resolver = new QueueResolver([new AnnotationJobRule()]);
        $context = new JobContext(jobClass: PostRunAnnotations::class);

        $resolution = $resolver->resolve($context);

        expect($resolution->queue)->toBe(Queue::Annotations);
    });

    it('routes long-running jobs to appropriate queue', function (): void {
        $resolver = new QueueResolver([new LongRunningJobRule()]);

        // Long running (>2 min but <5 min)
        $longContext = new JobContext(
            jobClass: 'App\Jobs\LongJob',
            estimatedDurationSeconds: 180
        );
        expect($resolver->resolve($longContext)->queue)->toBe(Queue::LongRunning);

        // Very long running (>5 min) goes to bulk
        $bulkContext = new JobContext(
            jobClass: 'App\Jobs\BulkJob',
            estimatedDurationSeconds: 400
        );
        expect($resolver->resolve($bulkContext)->queue)->toBe(Queue::Bulk);
    });

    it('applies rules in priority order', function (): void {
        // System rule (priority 1) should win over webhook rule (priority 5)
        $resolver = new QueueResolver([
            new WebhookJobRule(),
            new SystemJobRule(),
        ]);

        $context = new JobContext(
            jobClass: 'App\Jobs\SystemWebhook',
            isSystemJob: true,
            metadata: ['source' => 'webhook']
        );

        $resolution = $resolver->resolve($context);

        // System rule should fire first due to lower priority number
        expect($resolution->queue)->toBe(Queue::System);
        expect($resolution->forcedBy)->toBe('system_job');
    });

    it('returns trace for observability', function (): void {
        $resolver = new QueueResolver([
            new SystemJobRule(),
            new WebhookJobRule(),
        ]);

        $context = new JobContext(
            jobClass: 'App\Jobs\RegularJob',
            isSystemJob: false
        );

        $resolution = $resolver->resolve($context);

        expect($resolution->trace)->toBeArray();
        expect(count($resolution->trace))->toBe(2);

        // Both rules were evaluated but didn't apply
        expect($resolution->trace[0]['rule'])->toBe('system_job');
        expect($resolution->trace[0]['applied'])->toBeFalse();
        expect($resolution->trace[1]['rule'])->toBe('webhook_job');
        expect($resolution->trace[1]['applied'])->toBeFalse();
    });

    it('resolves to default queue when no rules apply', function (): void {
        $resolver = new QueueResolver([
            new SystemJobRule(),
            new WebhookJobRule(),
        ]);

        $context = new JobContext(
            jobClass: 'App\Jobs\GenericJob'
        );

        $resolution = $resolver->resolve($context);

        // Should resolve by score to highest priority queue
        expect($resolution->wasForced())->toBeFalse();
        expect($resolution->reason)->toBe('Selected by highest score');
    });

    it('is resolved from container with all rules', function (): void {
        $resolver = app(QueueResolver::class);

        expect($resolver)->toBeInstanceOf(QueueResolver::class);
        expect(count($resolver->getRules()))->toBe(5);
    });
});
