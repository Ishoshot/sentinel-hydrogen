<?php

declare(strict_types=1);

namespace App\Services\Commands\Formatters;

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
