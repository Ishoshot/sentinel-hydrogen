<?php

declare(strict_types=1);

namespace App\Actions\Runs;

use Illuminate\Support\Facades\DB;

final class ResolveRunQueryExpressions
{
    /**
     * Resolve SQL expression for effective pull request number across database drivers.
     */
    public function effectivePrNumber(): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "COALESCE(pr_number, (metadata->>'pull_request_number')::int)",
            default => "COALESCE(pr_number, CAST(json_extract(metadata, '$.pull_request_number') AS INTEGER))",
        };
    }

    /**
     * Resolve SQL expression for effective pull request title across database drivers.
     */
    public function effectivePrTitle(): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "COALESCE(pr_title, metadata->>'pull_request_title')",
            default => "COALESCE(pr_title, json_extract(metadata, '$.pull_request_title'))",
        };
    }
}
