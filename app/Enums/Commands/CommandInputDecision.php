<?php

declare(strict_types=1);

namespace App\Enums\Commands;

enum CommandInputDecision: string
{
    case Allow = 'allow';
    case Caution = 'caution';
    case Block = 'block';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve a deterministic priority for policy merges.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Allow => 1,
            self::Caution => 2,
            self::Block => 3,
        };
    }
}
