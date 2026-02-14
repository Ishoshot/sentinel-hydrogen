<?php

declare(strict_types=1);

namespace App\Actions\Commands\Support;

final class IssueCommentBodyFormatter
{
    /**
     * Format a Sentinel issue comment body.
     */
    public function format(string $message): string
    {
        return '**Sentinel**: '.$message;
    }
}
