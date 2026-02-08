<?php

declare(strict_types=1);

namespace App\Actions\Admin\Briefings;

use App\Models\Briefing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * List briefing templates with optional filters.
 */
final readonly class ListBriefings
{
    /**
     * List briefings with pagination.
     *
     * @return LengthAwarePaginator<int, Briefing>
     */
    public function handle(
        bool $activeOnly = false,
        bool $systemOnly = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = Briefing::query();

        if ($activeOnly) {
            $query->active();
        }

        if ($systemOnly) {
            $query->system();
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate($perPage);
    }
}
