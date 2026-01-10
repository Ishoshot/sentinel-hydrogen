<?php

declare(strict_types=1);

return [
    'default_policy' => [
        'policy_version' => 1,
        'enabled_rules' => [
            'summary_only',
        ],
        'severity_thresholds' => [
            'comment' => 'medium',
        ],
        'comment_limits' => [
            'max_inline_comments' => 10,
        ],
        'ignored_paths' => [],
    ],
];
