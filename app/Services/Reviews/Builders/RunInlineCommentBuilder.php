<?php

declare(strict_types=1);

namespace App\Services\Reviews\Builders;

use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Builds inline GitHub review comments from findings.
 */
final class RunInlineCommentBuilder
{
    private const int INLINE_IMPACT_MAX_LENGTH = 140;

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
        $body .= "#### {$finding->title}\n\n";
        $body .= $finding->description.PHP_EOL;

        if ($includeSuggestions) {
            $body .= $this->formatCodeSuggestion($metadata);
        }

        $impact = isset($metadata['impact']) && is_string($metadata['impact']) ? $metadata['impact'] : null;
        if ($impact !== null && $impact !== '') {
            if (mb_strlen($impact) <= self::INLINE_IMPACT_MAX_LENGTH) {
                $body .= "\n**🧐 How this affects you**\n{$impact}\n";
            } else {
                $body .= "\n<details>\n<summary>🧐 How this affects you</summary>\n<br>\n\n";
                $body .= "\n{$impact}\n\n";
                $body .= "</details>\n\n";
            }
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
        $suggestion = is_string($metadata['suggestion'] ?? null) ? $metadata['suggestion'] : null;
        $hasExplanation = $explanation !== null && $explanation !== '';

        if (
            ($replacementCode === null || $replacementCode === '')
            && ($suggestion === null || $suggestion === '')
            && ! $hasExplanation
        ) {
            return '';
        }

        $body = "\n";

        if ($hasExplanation) {
            $body .= "<details>\n<summary>💡 Why this suggestion?</summary>\n<br>\n\n";
            $body .= "\n{$explanation}\n\n";
            $body .= "</details>\n\n";
        }

        if ($replacementCode !== null && $replacementCode !== '') {
            $currentCode = is_string($metadata['current_code'] ?? null) ? $metadata['current_code'] : null;
            $replacementCode = $this->normalizeReplacementIndentation($currentCode, $replacementCode);

            $body .= "<details>\n<summary>📝 Committable suggestion</summary>\n<br>\n\n";
            $body .= "\n";
            $body .= "> **⚠️ Review before applying**\n";
            $body .= "> Before committing, confirm this patch correctly replaces the intended highlighted code, introduces no missing lines or indentation issues, and passes targeted testing and performance validation.\n\n";

            $body .= "```suggestion\n".$replacementCode;

            if (! str_ends_with($replacementCode, "\n")) {
                $body .= "\n";
            }

            return $body."```\n\n</details>\n\n";
        }

        if ($suggestion !== null && $suggestion !== '') {
            return $body.sprintf('**Suggestion:** %s%s', $suggestion, PHP_EOL);
        }

        return $body;
    }

    /**
     * Normalize replacement code indentation to match current code.
     *
     * LLMs often strip leading whitespace from replacement_code in JSON output.
     * This detects the indentation gap between current_code and replacement_code,
     * then pads the replacement to match.
     */
    private function normalizeReplacementIndentation(?string $currentCode, string $replacementCode): string
    {
        if ($currentCode === null || $currentCode === '') {
            return $replacementCode;
        }

        $currentIndent = $this->detectMinIndentation($currentCode);
        $replacementIndent = $this->detectMinIndentation($replacementCode);

        if ($currentIndent <= 0 || $replacementIndent >= $currentIndent) {
            return $replacementCode;
        }

        $padding = str_repeat(' ', $currentIndent - $replacementIndent);
        $lines = explode("\n", $replacementCode);
        $padded = array_map(
            fn (string $line): string => mb_trim($line) === '' ? $line : $padding.$line,
            $lines,
        );

        return implode("\n", $padded);
    }

    /**
     * Detect the minimum leading whitespace across non-empty lines.
     */
    private function detectMinIndentation(string $code): int
    {
        $min = PHP_INT_MAX;

        foreach (explode("\n", $code) as $line) {
            if (mb_trim($line) === '') {
                continue;
            }

            $indent = mb_strlen($line) - mb_strlen(mb_ltrim($line));
            $min = min($min, $indent);
        }

        return $min === PHP_INT_MAX ? 0 : $min;
    }
}
