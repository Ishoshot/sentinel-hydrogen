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
     * @return LengthAwarePaginator<AiOption>
     */
    public function handle(
        ?AiProvider $provider = null,
        bool $activeOnly = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return AiOption::query()
            ->when($provider !== null, fn ($query) => $query->forProvider($provider))
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('provider')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
