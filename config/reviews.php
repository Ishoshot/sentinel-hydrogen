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
    /*
    |--------------------------------------------------------------------------
    | Impact Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for cross-reference impact detection. When code in a PR modifies
    | functions, classes, or methods, the system searches the code index for
    | callers/references and includes impacted files in the review context.
    |
    */

    'impact_analysis' => [
        // Maximum symbols to search for references
        'max_symbols' => (int) env('REVIEW_IMPACT_MAX_SYMBOLS', 25),

        // Maximum impacted files to include in context
        'max_files' => (int) env('REVIEW_IMPACT_MAX_FILES', 20),

        // Maximum file size in bytes to include
        'max_file_size' => (int) env('REVIEW_IMPACT_MAX_FILE_SIZE', 50000), // 50KB

        // Search results per symbol before deduplication
        'search_limit_per_symbol' => (int) env('REVIEW_IMPACT_SEARCH_LIMIT', 50),

        // Minimum relevance score (0.0-1.0) to include a file
        'min_relevance_score' => (float) env('REVIEW_IMPACT_MIN_SCORE', 0.3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Review Policy Configuration
    |--------------------------------------------------------------------------
    |
    | These defaults match SentinelConfig::default() / ReviewConfig::default().
    | When a repository has a .sentinel/config.yaml, values are merged on top.
    |
    */

    'default_policy' => [
        // Schema version
        'policy_version' => 1,

        // Default enabled categories (matches CategoriesConfig defaults)
        'enabled_rules' => [
            'security',
            'correctness',
            'performance',
            'maintainability',
            'testing',
            // 'style' and 'documentation' are disabled by default
        ],

        // Severity thresholds - 'low' matches SentinelConfigSeverity::Low default
        'severity_thresholds' => [
            'comment' => 'low',
        ],

        // Confidence thresholds - enforce high-confidence findings by default
        'confidence_thresholds' => [
            'finding' => 0.7,
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
