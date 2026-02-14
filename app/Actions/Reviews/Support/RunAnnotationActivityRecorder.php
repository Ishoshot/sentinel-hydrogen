<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Models\Run;

final readonly class RunAnnotationActivityRecorder
{
    /**
     * Create a new activity recorder instance.
     */
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Record activity after successful annotation publishing.
     */
    public function record(Run $run, int $count, RunAnnotationContext $context): void
    {
        $workspace = $run->workspace;
        if ($workspace === null) {
            return;
        }

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::AnnotationsPosted,
            description: sprintf(
                'Posted %d annotations for PR #%d in %s',
                $count,
                $context->pullRequestNumber,
                $context->fullName
            ),
            subject: $run,
            metadata: [
                'annotations_count' => $count,
                'pull_request_number' => $context->pullRequestNumber,
            ],
        );
    }
}
