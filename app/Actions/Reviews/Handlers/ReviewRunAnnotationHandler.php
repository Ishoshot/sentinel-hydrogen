<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Handlers;

use App\Jobs\Reviews\PostRunAnnotations;
use App\Models\Run;

final readonly class ReviewRunAnnotationHandler
{
    /**
     * Dispatch annotation posting when the run has findings.
     */
    public function dispatchIfNeeded(Run $run): void
    {
        if (! $run->findings()->exists()) {
            return;
        }

        PostRunAnnotations::dispatch($run->id)->delay(now()->addSeconds(5));
    }
}
