<?php

declare(strict_types=1);

namespace App\Services\Commands\Resolvers;

use App\Models\CommandRun;
use App\Models\Finding;
use App\Models\Run;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final readonly class SimilarRunsAndFindingsResolver
{
    /**
     * Resolve similar historical runs and findings for issue brainstorming.
     *
     * @return array{
     *     runs: array<int, array{run_id: int, status: string, pr_number: int|null, pr_title: string, findings_count: int, created_at: string|null}>,
     *     findings: array<int, array{finding_id: int, severity: string, category: string, title: string, file_path: string|null, confidence: float|null, run_id: int, pr_number: int|null, pr_title: string, created_at: string|null}>
     * }
     */
    public function resolve(CommandRun $commandRun, ?string $query = null, int $runLimit = 5, int $findingLimit = 8): array
    {
        $repository = $commandRun->repository;
        $workspace = $commandRun->workspace;

        if ($repository === null || $workspace === null) {
            return ['runs' => [], 'findings' => []];
        }

        $rawQuery = mb_substr($query ?: $commandRun->query, 0, 400);
        $tokens = $this->extractTokens($rawQuery);
        $searchText = $this->buildSearchText($tokens);
        $lookbackStart = now()->subMonths(9);

        $runs = $this->queryRuns(
            workspaceId: $workspace->id,
            repositoryId: $repository->id,
            tokens: $tokens,
            searchText: $searchText,
            lookbackStart: $lookbackStart,
            limit: max(1, min($runLimit, 20)),
        );

        $findings = $this->queryFindings(
            workspaceId: $workspace->id,
            repositoryId: $repository->id,
            tokens: $tokens,
            searchText: $searchText,
            lookbackStart: $lookbackStart,
            limit: max(1, min($findingLimit, 30)),
        );

        return ['runs' => $runs, 'findings' => $findings];
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, array{run_id: int, status: string, pr_number: int|null, pr_title: string, findings_count: int, created_at: string|null}>
     */
    private function queryRuns(
        int $workspaceId,
        int $repositoryId,
        array $tokens,
        ?string $searchText,
        Carbon $lookbackStart,
        int $limit,
    ): array {
        $query = Run::query()
            ->where('workspace_id', $workspaceId)
            ->where('repository_id', $repositoryId)
            ->where('created_at', '>=', $lookbackStart)
            ->withCount('findings')
            ->latest('created_at');

        $this->applyRunTokenFilters($query, $tokens, $searchText);

        return $query->limit($limit)
            ->get()
            ->map(static fn (Run $run): array => [
                'run_id' => $run->id,
                'status' => $run->status->value,
                'pr_number' => $run->getEffectivePrNumber(),
                'pr_title' => $run->getEffectivePrTitle() ?? '',
                'findings_count' => (int) ($run->findings_count ?? 0),
                'created_at' => $run->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, array{finding_id: int, severity: string, category: string, title: string, file_path: string|null, confidence: float|null, run_id: int, pr_number: int|null, pr_title: string, created_at: string|null}>
     */
    private function queryFindings(
        int $workspaceId,
        int $repositoryId,
        array $tokens,
        ?string $searchText,
        Carbon $lookbackStart,
        int $limit,
    ): array {
        $query = Finding::query()
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $lookbackStart)
            ->whereHas('run', static fn (Builder $runQuery): Builder => $runQuery->where('repository_id', $repositoryId))
            ->with(['run:id,pr_number,pr_title,metadata,status,created_at'])
            ->latest('created_at');

        $this->applyFindingTokenFilters($query, $tokens, $searchText);

        return $query->limit($limit)
            ->get()
            ->map(static function (Finding $finding): array {
                $run = $finding->run;

                return [
                    'finding_id' => $finding->id,
                    'severity' => $finding->severity?->value ?? 'unknown',
                    'category' => $finding->category?->value ?? 'unknown',
                    'title' => $finding->title,
                    'file_path' => $finding->file_path,
                    'confidence' => is_numeric($finding->confidence) ? (float) $finding->confidence : null,
                    'run_id' => $run?->id ?? 0,
                    'pr_number' => $run?->getEffectivePrNumber(),
                    'pr_title' => (string) ($run?->getEffectivePrTitle() ?? ''),
                    'created_at' => $finding->created_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @param  Builder<Run>  $query
     * @param  array<int, string>  $tokens
     */
    private function applyRunTokenFilters(Builder $query, array $tokens, ?string $searchText): void
    {
        if ($tokens === [] && $searchText === null) {
            return;
        }

        $query->where(function (Builder $tokenQuery) use ($tokens, $searchText): void {
            $hasPredicate = false;

            if ($searchText !== null && $this->supportsFullText($tokenQuery)) {
                $tokenQuery->whereFullText(['pr_title', 'base_branch', 'head_branch'], $searchText);
                $hasPredicate = true;
            }

            foreach ($tokens as $token) {
                $like = '%'.$token.'%';
                $method = $hasPredicate ? 'orWhere' : 'where';

                $tokenQuery->{$method}(function (Builder $likeQuery) use ($like): void {
                    $likeQuery
                        ->whereRaw("LOWER(COALESCE(pr_title, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(base_branch, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(head_branch, '')) LIKE ?", [$like]);
                });

                $hasPredicate = true;
            }
        });
    }

    /**
     * @param  Builder<Finding>  $query
     * @param  array<int, string>  $tokens
     */
    private function applyFindingTokenFilters(Builder $query, array $tokens, ?string $searchText): void
    {
        if ($tokens === [] && $searchText === null) {
            return;
        }

        $query->where(function (Builder $tokenQuery) use ($tokens, $searchText): void {
            $hasPredicate = false;

            if ($searchText !== null && $this->supportsFullText($tokenQuery)) {
                $tokenQuery->whereFullText(['title', 'description', 'file_path'], $searchText);
                $hasPredicate = true;
            }

            foreach ($tokens as $token) {
                $like = '%'.$token.'%';
                $method = $hasPredicate ? 'orWhere' : 'where';

                $tokenQuery->{$method}(function (Builder $likeQuery) use ($like): void {
                    $likeQuery
                        ->whereRaw("LOWER(COALESCE(title, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(file_path, '')) LIKE ?", [$like]);
                });

                $hasPredicate = true;
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function extractTokens(string $text): array
    {
        preg_match_all('/[a-z][a-z0-9_]{3,}/i', mb_strtolower($text), $matches);
        $tokens = $matches[0];
        $stopwords = [
            'issue', 'with', 'from', 'that', 'this', 'there', 'when',
            'where', 'what', 'should', 'could', 'would', 'have', 'into',
            'about', 'please', 'help', 'need',
        ];

        $tokens = array_values(array_filter(
            $tokens,
            static fn (string $token): bool => ! in_array($token, $stopwords, true)
        ));

        return array_slice(array_values(array_unique($tokens)), 0, 8);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function buildSearchText(array $tokens): ?string
    {
        if ($tokens === []) {
            return null;
        }

        return implode(' ', $tokens);
    }

    /**
     * @param  Builder<Run>|Builder<Finding>  $query
     */
    private function supportsFullText(Builder $query): bool
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        return in_array($driver, ['pgsql', 'mysql', 'mariadb'], true);
    }
}
