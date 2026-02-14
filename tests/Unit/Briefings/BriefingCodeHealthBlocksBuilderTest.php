<?php

declare(strict_types=1);

use App\Services\Briefings\Slides\BriefingCodeHealthBlocksBuilder;

it('builds metrics and critical findings blocks for code health', function (): void {
    $builder = new BriefingCodeHealthBlocksBuilder;

    $blocks = $builder->build([
        'total_findings' => 10,
        'critical_issues' => 2,
        'high_issues' => 3,
        'medium_issues' => 5,
        'top_critical_findings' => [
            ['id' => 1, 'title' => 'SQL injection', 'file_path' => 'app/Auth.php', 'line_start' => 42],
        ],
    ]);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]->type)->toBe('metrics')
        ->and($blocks[1]->type)->toBe('list')
        ->and($blocks[1]->data['title'])->toBe('Top Critical Findings');
});

it('builds only metrics block when critical findings are missing', function (): void {
    $builder = new BriefingCodeHealthBlocksBuilder;

    $blocks = $builder->build([
        'total_findings' => 0,
        'critical_issues' => 0,
        'high_issues' => 0,
        'medium_issues' => 0,
    ]);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->type)->toBe('metrics');
});
