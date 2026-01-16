<?php

declare(strict_types=1);

namespace App\Services\Semantic;

use App\Services\Semantic\Contracts\SemanticAnalyzerInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

final class SemanticAnalyzerService implements SemanticAnalyzerInterface
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

    /**
     * Get the path to the semantic analyzer binary.
     */
    private function getBinaryPath(): string
    {
        return base_path(self::BINARY_PATH);
    }

    /**
     * Check if the file extension is supported for semantic analysis.
     */
    private function isSupported(string $extension): bool
    {
        return in_array($extension, [
            // Core languages
            'php',
            'js', 'mjs', 'cjs', 'jsx',
            'ts', 'tsx',
            'py',
            'go',
            'rs',
            // JVM languages
            'java',
            'kt', 'kts',
            // .NET
            'cs',
            // Dynamic languages
            'rb',
            // Apple ecosystem
            'swift',
            // Systems languages
            'c', 'h',
            'cpp', 'cc', 'cxx', 'hpp', 'hxx',
            // Frontend frameworks
            'vue',
            'svelte',
            // Web fundamentals
            'html', 'htm',
            'css', 'scss', 'sass',
            // Data & config
            'sql',
            'yaml', 'yml',
            // Shell
            'sh', 'bash', 'zsh',
        ], true);
    }
}
