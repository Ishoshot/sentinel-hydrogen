<?php

declare(strict_types=1);

namespace App\Services\Semantic\Contracts;

interface SemanticAnalyzerInterface
{
    /**
     * Analyze a single file and return semantic information.
     *
     * @return array<string, mixed>|null
     */
    public function analyzeFile(string $content, string $filename): ?array;

    /**
     * Analyze multiple files in batch.
     *
     * @param  array<string, string>  $files  filename => content
     * @return array<string, array<string, mixed>> filename => semantics
     */
    public function analyzeFiles(array $files): array;
}
