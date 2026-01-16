<?php

declare(strict_types=1);

namespace App\Actions\Admin\AiOptions;

use App\Enums\AiProvider;
use App\Models\AiOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * List AI options with optional filters.
 */
final readonly class ListAiOptions
{
    /**
     * List AI options with pagination.
     *
     * @return LengthAwarePaginator<int, AiOption>
     */
    public function handle(
        ?AiProvider $provider = null,
        bool $activeOnly = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = AiOption::query();

        if ($provider instanceof AiProvider) {
            $query->forProvider($provider);
        }

        if ($activeOnly) {
            $query->active();
        }

        return $query
            ->orderBy('provider')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
