<?php

declare(strict_types=1);

namespace App\Enums\CodeIndexing;

enum ChunkType: string
{
    case File = 'file';
    case ClassChunk = 'class';
    case Method = 'method';
    case Function = 'function';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
