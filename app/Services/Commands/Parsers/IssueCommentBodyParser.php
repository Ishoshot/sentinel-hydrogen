<?php

declare(strict_types=1);

namespace App\Services\Commands\Parsers;

final class IssueCommentBodyParser
{
    /**
     * Parse/format a Sentinel issue comment body.
     */
    public function format(string $message): string
    {
        return '**Sentinel**: '.$message;
    }
}
