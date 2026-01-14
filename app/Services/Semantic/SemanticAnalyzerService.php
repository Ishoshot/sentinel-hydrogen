<?php

declare(strict_types=1);

namespace App\Services\Semantic;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

final class SemanticAnalyzerService
{
    private const string BINARY_PATH = 'bin/semantic-analyzer';

    private const int TIMEOUT_SECONDS = 30;

    /**
     * Analyze a single file and return semantic information.
     *
     * @return array<string, mixed>|null
     */
    public function analyzeFile(string $content, string $filename): ?array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (! $this->isSupported($extension)) {
            return null;
        }

        $binaryPath = $this->getBinaryPath();
        if (! file_exists($binaryPath)) {
            Log::warning('SemanticAnalyzer: Binary not found', ['path' => $binaryPath]);

            return null;
        }

        $input = json_encode([
            'filename' => $filename,
            'content' => $content,
            'extension' => $extension,
        ], JSON_THROW_ON_ERROR);

        $result = Process::timeout(self::TIMEOUT_SECONDS)
            ->input($input)
            ->run($binaryPath);

        if (! $result->successful()) {
            Log::warning('SemanticAnalyzer: Failed to analyze file', [
                'filename' => $filename,
                'error' => $result->errorOutput(),
            ]);

            return null;
        }

        /** @var array<string, mixed>|null $output */
        $output = json_decode($result->output(), true);

        return is_array($output) ? $output : null;
    }

    /**
     * Analyze multiple files in batch.
     *
     * @param  array<string, string>  $files  filename => content
     * @return array<string, array<string, mixed>> filename => semantics
     */
    public function analyzeFiles(array $files): array
    {
        $results = [];

        foreach ($files as $filename => $content) {
            $result = $this->analyzeFile($content, $filename);
            if ($result !== null) {
                $results[$filename] = $result;
            }
        }

        return $results;
    }

    private function getBinaryPath(): string
    {
        return base_path(self::BINARY_PATH);
    }

    private function isSupported(string $extension): bool
    {
        return in_array($extension, [
            'php', 'js', 'mjs', 'ts', 'tsx', 'jsx',
            'py', 'go', 'rs', 'java', 'kt', 'cs',
            'rb', 'swift', 'c', 'cpp', 'h', 'hpp',
            'vue', 'svelte', 'html', 'css', 'scss',
            'sql', 'sh', 'bash', 'yaml', 'yml',
        ], true);
    }
}
