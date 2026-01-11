<?php

declare(strict_types=1);

/**
 * Default review policy configuration.
 *
 * These defaults match SentinelConfig::default() / ReviewConfig::default().
 * When a repository has a .sentinel/config.yaml, values are merged on top.
 *
 * @see App\DataTransferObjects\SentinelConfig\ReviewConfig
 * @see App\Services\Reviews\ReviewPolicyResolver
 */
return [
    'default_policy' => [
        // Schema version
        'policy_version' => 1,

        // Default enabled categories (matches CategoriesConfig defaults)
        'enabled_rules' => [
            'security',
            'correctness',
            'performance',
            'maintainability',
            // 'style' is disabled by default
        ],

        // Severity thresholds - 'low' matches SentinelConfigSeverity::Low default
        'severity_thresholds' => [
            'comment' => 'low',
        ],

        // Comment limits - 25 matches ReviewConfig::maxFindings default
        'comment_limits' => [
            'max_inline_comments' => 25,
        ],

        // File paths to ignore (merged with .sentinel/config.yaml paths.ignore)
        'ignored_paths' => [],

        // Review tone - matches SentinelConfigTone::Constructive default
        'tone' => 'constructive',

        // Response language (ISO 639-1 code)
        'language' => 'en',

        // Custom focus areas (empty by default)
        'focus' => [],
    ],
];
