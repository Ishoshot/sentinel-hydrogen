<?php

declare(strict_types=1);

return [
    'payload' => [
        'max_size' => 1024 * 1024,
        'max_nesting_depth' => 10,
        'max_calls' => 50,
        'max_components' => (int) env('LIVEWIRE_MAX_COMPONENTS', 60),
    ],
];
