<?php

declare(strict_types=1);

namespace App\Actions\Admin\Promotions;

use App\Models\Promotion;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * List promotions with optional filters.
 */
final readonly class ListPromotions
{
    /**
     * List promotions with filters.
     *
     * @return LengthAwarePaginator<int, Promotion>
     */
    public function handle(bool $activeOnly = false, bool $validOnly = false, int $perPage = 15): LengthAwarePaginator
    {
        $query = Promotion::query()->orderByDesc('created_at');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($validOnly) {
            $query->valid();
        }

        return $query->paginate($perPage);
    }
}
