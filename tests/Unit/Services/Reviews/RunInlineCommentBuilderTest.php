<?php

declare(strict_types=1);

use App\Enums\Reviews\FindingCategory;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use App\Services\Reviews\Builders\RunInlineCommentBuilder;
use App\Services\Reviews\ValueObjects\AnnotationConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->builder = new RunInlineCommentBuilder;
    $this->config = new AnnotationConfig(
        style: 'inline',
        postThreshold: 'low',
        grouped: false,
        includeSuggestions: false,
    );
});

function createFinding(array $attributes = []): Finding
{
    $defaults = [
        'id' => 1,
        'file_path' => 'src/app.php',
        'line_start' => 10,
        'line_end' => 15,
        'severity' => SentinelConfigSeverity::High,
        'category' => FindingCategory::Security,
        'title' => 'SQL Injection',
        'description' => 'User input is not sanitized',
        'confidence' => 0.95,
        'metadata' => [],
    ];

    return (new Finding)->forceFill(array_merge($defaults, $attributes));
}

it('builds inline comments from findings', function (): void {
    $finding = createFinding();
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments)->toHaveCount(1);
    expect($comments[0]['path'])->toBe('src/app.php');
    expect($comments[0]['start_line'])->toBe(10);
    expect($comments[0]['line'])->toBe(15);
    expect($comments[0]['start_side'])->toBe('RIGHT');
    expect($comments[0]['side'])->toBe('RIGHT');
});

it('uses line_start + 1 when line_end is null', function (): void {
    $finding = createFinding(['line_end' => null]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments)->toHaveCount(1);
    expect($comments[0]['line'])->toBe(11);
});

it('skips findings with no file_path', function (): void {
    Log::shouldReceive('warning')->once()->with('Finding has no file path', ['finding_id' => 1]);

    $finding = createFinding(['file_path' => null]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments)->toBeEmpty();
});

it('skips findings with no line_start', function (): void {
    Log::shouldReceive('warning')->once()->with('Finding has no line start', ['finding_id' => 2]);

    $finding = createFinding(['id' => 2, 'line_start' => null]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments)->toBeEmpty();
});

it('formats critical severity badge', function (): void {
    $finding = createFinding(['severity' => SentinelConfigSeverity::Critical]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('**:red_circle: Critical**');
});

it('formats high severity badge', function (): void {
    $finding = createFinding(['severity' => SentinelConfigSeverity::High]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('**:orange_circle: High**');
});

it('formats medium severity badge', function (): void {
    $finding = createFinding(['severity' => SentinelConfigSeverity::Medium]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('**:yellow_circle: Medium**');
});

it('formats low severity badge', function (): void {
    $finding = createFinding(['severity' => SentinelConfigSeverity::Low]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('**:white_circle: Low**');
});

it('formats info severity badge', function (): void {
    $finding = createFinding(['severity' => SentinelConfigSeverity::Info]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('**:blue_circle: Info**');
});

it('includes category in comment body', function (): void {
    $finding = createFinding(['category' => FindingCategory::Performance]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('`performance`');
});

it('includes title and description in comment body', function (): void {
    $finding = createFinding([
        'title' => 'Memory Leak',
        'description' => 'Objects are not released',
    ]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('### Memory Leak');
    expect($comments[0]['body'])->toContain('Objects are not released');
});

it('includes confidence percentage when present', function (): void {
    $finding = createFinding(['confidence' => 0.85]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('`Confidence: 85%`');
});

it('omits confidence when null', function (): void {
    $finding = createFinding(['confidence' => null]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->not->toContain('Confidence:');
});

it('includes impact from metadata when present', function (): void {
    $finding = createFinding(['metadata' => ['impact' => 'Data could be corrupted']]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('**🧐 How this affects you**');
    expect($comments[0]['body'])->toContain('Data could be corrupted');
    expect($comments[0]['body'])->not->toContain('<details>');
});

it('omits impact when not in metadata', function (): void {
    $finding = createFinding(['metadata' => []]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->not->toContain('How this affects you');
});

it('renders long impact in collapsible block', function (): void {
    $longImpact = str_repeat('Impact sentence. ', 12);
    $finding = createFinding(['metadata' => ['impact' => $longImpact]]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('<details>');
    expect($comments[0]['body'])->toContain('<summary>🧐 How this affects you</summary>');
    expect($comments[0]['body'])->toContain($longImpact);
});

it('includes replacement code suggestion when enabled', function (): void {
    $config = $this->config->with(includeSuggestions: true);
    $finding = createFinding([
        'metadata' => [
            'replacement_code' => 'return $sanitized;',
            'explanation' => 'Sanitize inputs before use',
        ],
    ]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $config);

    expect($comments[0]['body'])->toContain('<details>');
    expect($comments[0]['body'])->toContain('<summary>📝 Committable suggestion</summary>');
    expect($comments[0]['body'])->toContain('**⚠️ Review before applying**');
    expect($comments[0]['body'])->toContain('Before committing, confirm this patch correctly replaces the intended highlighted code');
    expect($comments[0]['body'])->toContain('```suggestion');
    expect($comments[0]['body'])->toContain('return $sanitized;');
    expect($comments[0]['body'])->toContain('<summary>💡 Why this suggestion?</summary>');
    expect($comments[0]['body'])->toContain('Sanitize inputs before use');
    expect($comments[0]['body'])->toContain('</details>');
});

it('includes text suggestion when no replacement code', function (): void {
    $config = $this->config->with(includeSuggestions: true);
    $finding = createFinding([
        'metadata' => ['suggestion' => 'Consider using prepared statements'],
    ]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $config);

    expect($comments[0]['body'])->toContain('**Suggestion:** Consider using prepared statements');
});

it('does not include suggestions when disabled', function (): void {
    $finding = createFinding([
        'metadata' => [
            'replacement_code' => 'fixed code',
            'suggestion' => 'Fix this',
        ],
    ]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->not->toContain('```suggestion');
    expect($comments[0]['body'])->not->toContain('**Suggestion:**');
});

it('builds multiple comments for multiple findings', function (): void {
    $findingA = createFinding([
        'id' => 1,
        'file_path' => 'src/auth.php',
        'line_start' => 5,
        'title' => 'Finding A',
    ]);
    $findingB = createFinding([
        'id' => 2,
        'file_path' => 'src/db.php',
        'line_start' => 20,
        'title' => 'Finding B',
    ]);
    $findings = new Collection([$findingA, $findingB]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments)->toHaveCount(2);
    expect($comments[0]['path'])->toBe('src/auth.php');
    expect($comments[1]['path'])->toBe('src/db.php');
});

it('returns empty array for empty collection', function (): void {
    $findings = new Collection;

    $comments = $this->builder->build($findings, $this->config);

    expect($comments)->toBeEmpty();
});

it('handles null category gracefully', function (): void {
    $finding = createFinding(['category' => null]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $this->config);

    expect($comments[0]['body'])->toContain('`unknown`');
});

it('normalizes replacement code indentation to match current code', function (): void {
    $config = $this->config->with(includeSuggestions: true);
    $finding = createFinding([
        'metadata' => [
            'current_code' => '        $rateLimit = $application->rate_limit ?? 10000;',
            'replacement_code' => '$rateLimit = $application->rate_limit ?? 60;',
        ],
    ]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $config);

    expect($comments[0]['body'])->toContain("```suggestion\n        \$rateLimit = \$application->rate_limit ?? 60;");
});

it('normalizes multi-line replacement code indentation', function (): void {
    $config = $this->config->with(includeSuggestions: true);
    $finding = createFinding([
        'metadata' => [
            'current_code' => "        Log::info('Incoming webhook payload', [\n            'application_id' => \$request->route('applicationId'),\n        ]);",
            'replacement_code' => "Log::info('Incoming webhook payload', [\n    'application_id' => \$request->route('applicationId'),\n    'payload_keys' => array_keys(\$request->all()),\n]);",
        ],
    ]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $config);

    expect($comments[0]['body'])->toContain("```suggestion\n        Log::info('Incoming webhook payload', [");
    expect($comments[0]['body'])->toContain("            'application_id' => \$request->route('applicationId'),");
});

it('does not normalize when replacement already has correct indentation', function (): void {
    $config = $this->config->with(includeSuggestions: true);
    $finding = createFinding([
        'metadata' => [
            'current_code' => '    return true;',
            'replacement_code' => '    return false;',
        ],
    ]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $config);

    expect($comments[0]['body'])->toContain("```suggestion\n    return false;");
});

it('does not normalize when current code has no indentation', function (): void {
    $config = $this->config->with(includeSuggestions: true);
    $finding = createFinding([
        'metadata' => [
            'current_code' => 'return true;',
            'replacement_code' => 'return false;',
        ],
    ]);
    $findings = new Collection([$finding]);

    $comments = $this->builder->build($findings, $config);

    expect($comments[0]['body'])->toContain("```suggestion\nreturn false;");
});
