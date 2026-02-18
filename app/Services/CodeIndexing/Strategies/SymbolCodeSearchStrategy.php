<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Strategies;

use App\Models\CodeEmbedding;
use App\Models\Repository;
use App\Services\CodeIndexing\Mappers\SymbolSearchResultMapper;
use App\Services\CodeIndexing\ValueObjects\CodeIndexScope;
use Illuminate\Database\Eloquent\Builder;

final readonly class SymbolCodeSearchStrategy
{
    /**
     * Create a new SymbolCodeSearchStrategy instance.
     */
    public function __construct(
        private SymbolSearchResultMapper $resultMapper,
    ) {}

    /**
     * @return array<int, array{file_path: string, symbol_name: string, chunk_type: \App\Enums\CodeIndexing\ChunkType|string, content: string, metadata: array<string, mixed>}>
     */
    public function execute(Repository $repository, string $symbolName, int $limit, ?CodeIndexScope $scope = null): array
    {
        $resolvedScope = $scope ?? CodeIndexScope::baseline();
        $results = CodeEmbedding::query()
            ->where('repository_id', $repository->id)
            ->where(function (Builder $builder) use ($symbolName): void {
                $builder->where('symbol_name', 'LIKE', '%'.$symbolName.'%')
                    ->orWhere('symbol_name', $symbolName);
            })
            ->whereIn('chunk_type', ['class', 'method', 'function'])
            ->whereHas('codeIndex', function (Builder $builder) use ($resolvedScope): void {
                $builder
                    ->where('scope_type', $resolvedScope->type->value)
                    ->where('scope_ref', $resolvedScope->ref);
            })
            ->with('codeIndex:id,file_path,file_type')
            ->limit($limit)
            ->get();

        return $results
            ->map(fn (CodeEmbedding $embedding): array => $this->resultMapper->map($embedding))
            ->all();
    }
}
