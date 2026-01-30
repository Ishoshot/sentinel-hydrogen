<?php

declare(strict_types=1);

namespace App\Enums\AI;

enum TokenCountMode: string
{
    case Estimate = 'estimate';
    case Precise = 'precise';
}
