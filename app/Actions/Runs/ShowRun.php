<?php

declare(strict_types=1);

namespace App\Actions\Runs;

use App\Models\Run;

/**
 * Show a run with its related data.
 */
final class ShowRun
{
    /**
     * Load and return the run with repository and findings.
     */
    public function handle(Run $run): Run
    {
        $run->load(['repository', 'findings.annotations']);

        return $run;
    }
}
