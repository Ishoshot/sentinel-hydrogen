<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

use App\Services\Context\ContextBag;
use App\Services\Reviews\ValueObjects\ReviewPolicy;

final readonly class ReviewRunContextResolution
{
    /**
     * Create a new context resolution value object.
     */
    public function __construct(
        public ContextBag $contextBag,
        public ReviewPolicy $policySnapshot,
    ) {}
}
