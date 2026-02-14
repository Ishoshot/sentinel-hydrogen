<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\Context\ContextBag;

/**
 * Extracts modified symbols by intersecting semantic ranges and patch line edits.
 */
final class ImpactModifiedSymbolExtractor
{
    /**
     * Create a new extractor instance.
     */
    public function __construct(
        private readonly ?ImpactPatchFileEntryFinder $fileEntryFinder = null,
        private readonly ?ImpactPatchModifiedLineParser $modifiedLineParser = null,
        private readonly ?ImpactSemanticSymbolExtractor $semanticSymbolExtractor = null,
    ) {}

    /**
     * Extract modified symbols from semantic analysis.
     *
     * @return array<int, array{name: string, type: string, file: string}>
     */
    public function extractModifiedSymbols(ContextBag $bag): array
    {
        $modifiedSymbols = [];

        foreach ($bag->semantics as $filename => $semanticData) {
            $fileEntry = $this->fileEntryFinder()->find($bag->files, $filename);
            if ($fileEntry === null) {
                continue;
            }

            $patch = $fileEntry['patch'];
            if (! is_string($patch)) {
                continue;
            }

            $modifiedLines = $this->modifiedLineParser()->parse($patch);
            $modifiedSymbols = [
                ...$modifiedSymbols,
                ...$this->semanticSymbolExtractor()->extract($filename, $semanticData, $modifiedLines),
            ];
        }

        return $modifiedSymbols;
    }

    private function fileEntryFinder(): ImpactPatchFileEntryFinder
    {
        return $this->fileEntryFinder ?? new ImpactPatchFileEntryFinder;
    }

    private function modifiedLineParser(): ImpactPatchModifiedLineParser
    {
        return $this->modifiedLineParser ?? new ImpactPatchModifiedLineParser;
    }

    private function semanticSymbolExtractor(): ImpactSemanticSymbolExtractor
    {
        return $this->semanticSymbolExtractor ?? new ImpactSemanticSymbolExtractor;
    }
}
