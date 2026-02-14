<?php

declare(strict_types=1);

namespace App\Services\Commands\Support;

final readonly class PullRequestContextFormatter
{
    private const int MAX_DIFF_CHARS = 5000;

    private const int MAX_COMMENTS = 10;

    /**
     * @param  array<string, mixed>  $pr
     * @param  array<int, array<string, mixed>>  $files
     * @param  array<int, array<string, mixed>>  $comments
     */
    public function format(array $pr, array $files, array $comments): string
    {
        $title = (string) ($pr['title'] ?? 'Untitled');
        $description = (string) ($pr['body'] ?? 'No description provided.');
        $baseBranch = is_array($pr['base'] ?? null) ? (string) ($pr['base']['ref'] ?? 'unknown') : 'unknown';
        $headBranch = is_array($pr['head'] ?? null) ? (string) ($pr['head']['ref'] ?? 'unknown') : 'unknown';
        $additions = (int) ($pr['additions'] ?? 0);
        $deletions = (int) ($pr['deletions'] ?? 0);
        $changedFiles = (int) ($pr['changed_files'] ?? count($files));

        $fileChanges = $this->formatFileChanges($files);
        $formattedComments = $this->formatComments($comments);

        $context = <<<CTX
## Pull Request Context

**Title**: {$title}

**Branch**: {$headBranch} → {$baseBranch}

**Stats**: {$changedFiles} files changed, +{$additions} / -{$deletions}

**Description**:
{$description}

### Changed Files

{$fileChanges}

CTX;

        if ($formattedComments !== '') {
            $context .= <<<CTX

### Recent Comments

{$formattedComments}

CTX;
        }

        return $context."---\n";
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function formatFileChanges(array $files): string
    {
        if ($files === []) {
            return 'No files changed.';
        }

        $totalDiffSize = 0;
        $formatted = [];

        foreach ($files as $file) {
            $filename = (string) ($file['filename'] ?? 'unknown');
            $status = (string) ($file['status'] ?? 'modified');
            $additions = (int) ($file['additions'] ?? 0);
            $deletions = (int) ($file['deletions'] ?? 0);
            $patch = (string) ($file['patch'] ?? '');

            $statusEmoji = match ($status) {
                'added' => '[+]',
                'removed' => '[-]',
                'renamed' => '[→]',
                default => '[M]',
            };

            $fileHeader = sprintf('%s `%s` (+%s / -%s)', $statusEmoji, $filename, $additions, $deletions);

            if ($patch !== '' && $totalDiffSize < self::MAX_DIFF_CHARS) {
                $patchToAdd = $patch;
                $remainingChars = self::MAX_DIFF_CHARS - $totalDiffSize;

                if (mb_strlen($patch) > $remainingChars) {
                    $patchToAdd = mb_substr($patch, 0, $remainingChars)."\n... (diff truncated)";
                }

                $totalDiffSize += mb_strlen($patchToAdd);
                $formatted[] = sprintf("%s\n```diff\n%s\n```", $fileHeader, $patchToAdd);
            } else {
                $formatted[] = $fileHeader;
            }
        }

        return implode("\n\n", $formatted);
    }

    /**
     * @param  array<int, array<string, mixed>>  $comments
     */
    private function formatComments(array $comments): string
    {
        if ($comments === []) {
            return '';
        }

        $recentComments = array_slice($comments, -self::MAX_COMMENTS);

        $formatted = array_map(function (array $comment): string {
            $user = $comment['user']['login'] ?? 'unknown';
            $body = $comment['body'] ?? '';

            if (mb_strlen($body) > 300) {
                $body = mb_substr($body, 0, 300).'...';
            }

            return sprintf('**@%s**: %s', $user, $body);
        }, $recentComments);

        return implode("\n\n", $formatted);
    }
}
