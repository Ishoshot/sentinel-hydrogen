<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Support;

use App\Models\CodeIndex;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Builder;

final readonly class KeywordCodeSearchExecutor
{
    /**
     * Create a new KeywordCodeSearchExecutor instance.
     */
    public function __construct(
        private KeywordSearchScorer $keywordScorer,
    ) {}

    /**
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>
     */
    public function execute(Repository $repository, string $query, int $limit, ?array $fileTypes): array
    {
        $searchTerms = $this->keywordScorer->extractTerms($query);

        if ($searchTerms === []) {
            return [];
        }

        $queryBuilder = $this->baseQuery($repository, $fileTypes);

        $queryBuilder->where(function (Builder $builder) use ($searchTerms): void {
            foreach ($searchTerms as $term) {
                $builder->orWhere('file_path', 'LIKE', '%'.$term.'%')
                    ->orWhere('content', 'LIKE', '%'.$term.'%');
            }
        });

        $results = $queryBuilder
            ->select(['id', 'file_path', 'file_type', 'content', 'structure', 'metadata'])
            ->limit($limit)
            ->get();
        /** @var \Illuminate\Database\Eloquent\Collection<int, CodeIndex> $results */

        return $results
            ->map(fn (CodeIndex $index): array => [
                'file_path' => $index->file_path,
                'content' => $this->keywordScorer->extractSnippet($index->content, $searchTerms),
                'score' => $this->keywordScorer->calculateScore($index, $searchTerms),
                'metadata' => [
                    'file_type' => $index->file_type,
                    'structure' => $index->structure,
                    'match_type' => 'keyword',
                ],
            ])
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * @param  array<string>|null  $fileTypes
     * @return Builder<CodeIndex>
     */
    private function baseQuery(Repository $repository, ?array $fileTypes): Builder
    {
        $queryBuilder = CodeIndex::query()->where('repository_id', $repository->id);

        if ($fileTypes !== null && $fileTypes !== []) {
            $queryBuilder->whereIn('file_type', $fileTypes);
        }

        return $queryBuilder;
    }
}
