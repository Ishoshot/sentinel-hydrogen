<?php

declare(strict_types=1);

namespace App\Services\Queue\Rules;

use App\Enums\Queue\Queue;
use App\Jobs\Reviews\PostRunAnnotations;
use App\Services\Queue\Contracts\QueueRule;
use App\Services\Queue\ValueObjects\JobContext;
use App\Services\Queue\ValueObjects\QueueRuleResult;

/**
 * Routes annotation jobs to the annotations queue.
 *
 * Annotations need to be posted promptly after review completion
 * but should not block review execution.
 */
final readonly class AnnotationJobRule implements QueueRule
{
    /**
     * Annotation job classes that should be routed to the annotations queue.
     *
     * @var array<int, class-string>
     */
    private const array ANNOTATION_JOB_CLASSES = [
        PostRunAnnotations::class,
    ];

    /**
     * Get the rule priority.
     */
    public function priority(): int
    {
        return 25;
    }

    /**
     * Check if this rule applies to the given context.
     */
    public function applies(JobContext $context): bool
    {
        return in_array($context->jobClass, self::ANNOTATION_JOB_CLASSES, true);
    }

    /**
     * Evaluate the rule and return a result.
     */
    public function evaluate(JobContext $context): QueueRuleResult
    {
        return QueueRuleResult::force(
            Queue::Annotations,
            'Annotation job routed to annotations queue'
        );
    }

    /**
     * Get the rule name.
     */
    public function name(): string
    {
        return 'annotation_job';
    }
}
