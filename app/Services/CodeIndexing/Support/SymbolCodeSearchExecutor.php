<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Support;

use App\Models\CodeEmbedding;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Builder;

final readonly class SymbolCodeSearchExecutor
{
    public function __construct(
        private SymbolSearchResultMapper $resultMapper,
    ) {}

    /**
     * @return array<int, array{file_path: string, symbol_name: string, chunk_type: \App\Enums\CodeIndexing\ChunkType|string, content: string, metadata: array<string, mixed>}>
     */
    public function execute(Repository $repository, string $symbolName, int $limit): array
    {
        $results = CodeEmbedding::query()
            ->where('repository_id', $repository->id)
            ->where(function (Builder $builder) use ($symbolName): void {
                $builder->where('symbol_name', 'LIKE', '%'.$symbolName.'%')
                    ->orWhere('symbol_name', $symbolName);
            })
            ->whereIn('chunk_type', ['class', 'method', 'function'])
            ->with('codeIndex:id,file_path,file_type')
            ->limit($limit)
            ->get();

        return $results
            ->map(fn (CodeEmbedding $embedding): array => $this->resultMapper->map($embedding))
            ->all();
    }
}
