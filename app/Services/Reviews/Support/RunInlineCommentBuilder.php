<?php

declare(strict_types=1);

namespace App\Services\Reviews\Support;

use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Builds inline GitHub review comments from findings.
 */
final class RunInlineCommentBuilder
{
    /**
     * @param  Collection<int, Finding>  $findings
     * @param  array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}  $config
     * @return array<int, array{path: string, line: int, side: string, body: string, start_line: int, start_side: string}>
     */
    public function build(Collection $findings, array $config): array
    {
        $comments = [];
        $includeSuggestions = $config['include_suggestions'];

        foreach ($findings as $finding) {
            if ($finding->file_path === null) {
                Log::warning('Finding has no file path', ['finding_id' => $finding->id]);

                continue;
            }

            if ($finding->line_start === null) {
                Log::warning('Finding has no line start', ['finding_id' => $finding->id]);

                continue;
            }

            $comments[] = [
                'path' => $finding->file_path,
                'start_line' => $finding->line_start,
                'line' => $finding->line_end ?? $finding->line_start + 1,
                'start_side' => 'RIGHT',
                'side' => 'RIGHT',
                'body' => $this->formatFindingComment($finding, $includeSuggestions),
            ];
        }

        return $comments;
    }

    /**
     * Format a finding as a markdown inline comment body.
     */
    private function formatFindingComment(Finding $finding, bool $includeSuggestions): string
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $finding->metadata ?? [];

        $severityBadge = match ($finding->severity) {
            SentinelConfigSeverity::Critical => '**:red_circle: Critical**',
            SentinelConfigSeverity::High => '**:orange_circle: High**',
            SentinelConfigSeverity::Medium => '**:yellow_circle: Medium**',
            SentinelConfigSeverity::Low => '**:white_circle: Low**',
            default => '**:blue_circle: Info**',
        };

        $categoryValue = $finding->category?->value ?? 'unknown';
        $body = "{$severityBadge} | `{$categoryValue}`\n\n";
        $body .= "### {$finding->title}\n\n";
        $body .= $finding->description.PHP_EOL;

        if ($includeSuggestions) {
            $body .= $this->formatCodeSuggestion($metadata);
        }

        $impact = isset($metadata['impact']) && is_string($metadata['impact']) ? $metadata['impact'] : null;
        if ($impact !== null && $impact !== '') {
            $body .= "\n_Impact: {$impact}_\n";
        }

        $confidence = $finding->confidence;
        if ($confidence !== null) {
            $confidencePercent = (int) round($confidence * 100);
            $body .= "\n`Confidence: {$confidencePercent}%`";
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function formatCodeSuggestion(array $metadata): string
    {
        $replacementCode = is_string($metadata['replacement_code'] ?? null) ? $metadata['replacement_code'] : null;
        $explanation = is_string($metadata['explanation'] ?? null) ? $metadata['explanation'] : null;

        if ($replacementCode !== null && $replacementCode !== '') {
            $body = "\n";

            if ($explanation !== null && $explanation !== '') {
                $body .= "**Why:** {$explanation}\n\n";
            }

            $body .= "```suggestion\n".$replacementCode;

            if (! str_ends_with($replacementCode, "\n")) {
                $body .= "\n";
            }

            return $body."```\n";
        }

        $suggestion = is_string($metadata['suggestion'] ?? null) ? $metadata['suggestion'] : null;

        if ($suggestion !== null && $suggestion !== '') {
            return "\n**Suggestion:** {$suggestion}\n";
        }

        return '';
    }
}
