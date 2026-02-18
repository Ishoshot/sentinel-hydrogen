<?php

declare(strict_types=1);

/**
 * Default review policy configuration.
 *
 * These defaults match SentinelConfig::default() / ReviewConfig::default().
 * When a repository has a .sentinel/config.yaml, values are merged on top.
 *
 * @see App\Services\SentinelConfig\ValueObjects\ReviewConfig
 * @see App\Services\Reviews\ReviewPolicyResolver
 */
return [
    'ack_comment_updates' => (bool) env('REVIEW_ACK_COMMENT_UPDATES', true),

    /*
    |--------------------------------------------------------------------------
    | File Context Configuration
    |--------------------------------------------------------------------------
    |
    | Controls how many changed files (and how large each file can be) are
    | fetched at full-content granularity for review context.
    |
    */

    'file_context' => [
        'max_files' => (int) env('REVIEW_FILE_CONTEXT_MAX_FILES', 10),
        'max_file_size' => (int) env('REVIEW_FILE_CONTEXT_MAX_FILE_SIZE', 50000), // 50KB
    ],

    /*
    |--------------------------------------------------------------------------
    | Semantic Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | Controls semantic analysis coverage over fetched full file contents.
    |
    */

    'semantic' => [
        'max_files' => (int) env('REVIEW_SEMANTIC_MAX_FILES', 15),
        'max_file_size' => (int) env('REVIEW_SEMANTIC_MAX_FILE_SIZE', 100000), // 100KB
    ],

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
    | Code Indexing Configuration
    |--------------------------------------------------------------------------
    |
    | Controls incremental indexing thresholds and indexing batch size.
    |
    */

    'indexing' => [
        'full_reindex_threshold' => (int) env('REVIEW_INDEXING_FULL_REINDEX_THRESHOLD', 500),
        'batch_size' => (int) env('REVIEW_INDEXING_BATCH_SIZE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pull Request Pre-Indexing
    |--------------------------------------------------------------------------
    |
    | Optional PR-scoped indexing for opened/synchronized/reopened pull
    | requests. When enabled, review-time search can blend PR-scoped index
    | results with baseline repository index results.
    |
    */

    'pr_preindex' => [
        'enabled' => (bool) env('REVIEW_PR_PREINDEX_ENABLED', false),
        'mode' => env('REVIEW_PR_PREINDEX_MODE', 'async'),

        'eligible_actions' => [
            'opened',
            'synchronize',
            'reopened',
        ],

        'eligibility' => [
            'tiers' => array_values(array_filter(array_map(
                static fn (string $tier): string => mb_trim($tier),
                explode(',', (string) env('REVIEW_PR_PREINDEX_TIERS', 'illuminate,orchestrate,sanctum'))
            ))),
            'max_files_changed' => (int) env('REVIEW_PR_PREINDEX_MAX_FILES_CHANGED', 120),
            'max_lines_changed' => (int) env('REVIEW_PR_PREINDEX_MAX_LINES_CHANGED', 8000),
        ],

        'indexing' => [
            'max_files' => (int) env('REVIEW_PR_PREINDEX_MAX_FILES', 40),
            'max_file_size' => (int) env('REVIEW_PR_PREINDEX_MAX_FILE_SIZE', 120000),
            'batch_size' => (int) env('REVIEW_PR_PREINDEX_BATCH_SIZE', 50),
        ],

        'hybrid_search' => [
            'enabled' => (bool) env('REVIEW_PR_HYBRID_SEARCH_ENABLED', true),
            'fallback_to_baseline' => (bool) env('REVIEW_PR_HYBRID_FALLBACK_TO_BASELINE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Adaptive Limit Profiles
    |--------------------------------------------------------------------------
    |
    | Optional tier-aware and PR-size-aware limit overrides. Static defaults
    | above remain the source of truth unless adaptive flags are enabled.
    |
    */

    'adaptive_limits' => [
        'enabled' => (bool) env('REVIEW_ADAPTIVE_LIMITS', false),

        'pr_size_buckets' => [
            'small' => ['max_files_changed' => 15, 'max_lines_changed' => 500],
            'medium' => ['max_files_changed' => 40, 'max_lines_changed' => 2000],
            'large' => ['max_files_changed' => 120, 'max_lines_changed' => 8000],
            'xlarge' => ['max_files_changed' => 1000000, 'max_lines_changed' => 1000000],
        ],

        'tiers' => [
            'foundation' => [
                'small' => [
                    'file_context' => ['max_files' => 10, 'max_file_size' => 50000],
                    'semantic' => ['max_files' => 15, 'max_file_size' => 100000],
                    'impact_analysis' => [
                        'max_symbols' => 25,
                        'max_files' => 20,
                        'max_file_size' => 50000,
                        'search_limit_per_symbol' => 50,
                        'min_relevance_score' => 0.3,
                    ],
                ],
                'medium' => [
                    'file_context' => ['max_files' => 9, 'max_file_size' => 50000],
                    'semantic' => ['max_files' => 12, 'max_file_size' => 90000],
                    'impact_analysis' => [
                        'max_symbols' => 20,
                        'max_files' => 16,
                        'max_file_size' => 45000,
                        'search_limit_per_symbol' => 40,
                        'min_relevance_score' => 0.35,
                    ],
                ],
                'large' => [
                    'file_context' => ['max_files' => 7, 'max_file_size' => 45000],
                    'semantic' => ['max_files' => 10, 'max_file_size' => 80000],
                    'impact_analysis' => [
                        'max_symbols' => 16,
                        'max_files' => 12,
                        'max_file_size' => 40000,
                        'search_limit_per_symbol' => 30,
                        'min_relevance_score' => 0.4,
                    ],
                ],
                'xlarge' => [
                    'file_context' => ['max_files' => 6, 'max_file_size' => 40000],
                    'semantic' => ['max_files' => 8, 'max_file_size' => 70000],
                    'impact_analysis' => [
                        'max_symbols' => 12,
                        'max_files' => 10,
                        'max_file_size' => 35000,
                        'search_limit_per_symbol' => 25,
                        'min_relevance_score' => 0.45,
                    ],
                ],
            ],
            'illuminate' => [
                'small' => [
                    'file_context' => ['max_files' => 14, 'max_file_size' => 70000],
                    'semantic' => ['max_files' => 20, 'max_file_size' => 140000],
                    'impact_analysis' => [
                        'max_symbols' => 32,
                        'max_files' => 26,
                        'max_file_size' => 70000,
                        'search_limit_per_symbol' => 70,
                        'min_relevance_score' => 0.28,
                    ],
                ],
                'medium' => [
                    'file_context' => ['max_files' => 12, 'max_file_size' => 65000],
                    'semantic' => ['max_files' => 18, 'max_file_size' => 130000],
                    'impact_analysis' => [
                        'max_symbols' => 28,
                        'max_files' => 22,
                        'max_file_size' => 65000,
                        'search_limit_per_symbol' => 60,
                        'min_relevance_score' => 0.3,
                    ],
                ],
                'large' => [
                    'file_context' => ['max_files' => 10, 'max_file_size' => 60000],
                    'semantic' => ['max_files' => 16, 'max_file_size' => 120000],
                    'impact_analysis' => [
                        'max_symbols' => 24,
                        'max_files' => 18,
                        'max_file_size' => 60000,
                        'search_limit_per_symbol' => 50,
                        'min_relevance_score' => 0.33,
                    ],
                ],
                'xlarge' => [
                    'file_context' => ['max_files' => 9, 'max_file_size' => 55000],
                    'semantic' => ['max_files' => 14, 'max_file_size' => 110000],
                    'impact_analysis' => [
                        'max_symbols' => 20,
                        'max_files' => 16,
                        'max_file_size' => 55000,
                        'search_limit_per_symbol' => 45,
                        'min_relevance_score' => 0.35,
                    ],
                ],
            ],
            'orchestrate' => [
                'small' => [
                    'file_context' => ['max_files' => 18, 'max_file_size' => 90000],
                    'semantic' => ['max_files' => 28, 'max_file_size' => 180000],
                    'impact_analysis' => [
                        'max_symbols' => 40,
                        'max_files' => 32,
                        'max_file_size' => 90000,
                        'search_limit_per_symbol' => 90,
                        'min_relevance_score' => 0.25,
                    ],
                ],
                'medium' => [
                    'file_context' => ['max_files' => 16, 'max_file_size' => 85000],
                    'semantic' => ['max_files' => 24, 'max_file_size' => 170000],
                    'impact_analysis' => [
                        'max_symbols' => 36,
                        'max_files' => 28,
                        'max_file_size' => 85000,
                        'search_limit_per_symbol' => 80,
                        'min_relevance_score' => 0.27,
                    ],
                ],
                'large' => [
                    'file_context' => ['max_files' => 14, 'max_file_size' => 80000],
                    'semantic' => ['max_files' => 22, 'max_file_size' => 160000],
                    'impact_analysis' => [
                        'max_symbols' => 32,
                        'max_files' => 24,
                        'max_file_size' => 80000,
                        'search_limit_per_symbol' => 70,
                        'min_relevance_score' => 0.3,
                    ],
                ],
                'xlarge' => [
                    'file_context' => ['max_files' => 12, 'max_file_size' => 75000],
                    'semantic' => ['max_files' => 20, 'max_file_size' => 150000],
                    'impact_analysis' => [
                        'max_symbols' => 28,
                        'max_files' => 22,
                        'max_file_size' => 75000,
                        'search_limit_per_symbol' => 60,
                        'min_relevance_score' => 0.32,
                    ],
                ],
            ],
            'sanctum' => [
                'small' => [
                    'file_context' => ['max_files' => 24, 'max_file_size' => 120000],
                    'semantic' => ['max_files' => 36, 'max_file_size' => 240000],
                    'impact_analysis' => [
                        'max_symbols' => 48,
                        'max_files' => 40,
                        'max_file_size' => 120000,
                        'search_limit_per_symbol' => 120,
                        'min_relevance_score' => 0.22,
                    ],
                ],
                'medium' => [
                    'file_context' => ['max_files' => 22, 'max_file_size' => 110000],
                    'semantic' => ['max_files' => 34, 'max_file_size' => 220000],
                    'impact_analysis' => [
                        'max_symbols' => 44,
                        'max_files' => 36,
                        'max_file_size' => 110000,
                        'search_limit_per_symbol' => 110,
                        'min_relevance_score' => 0.24,
                    ],
                ],
                'large' => [
                    'file_context' => ['max_files' => 20, 'max_file_size' => 100000],
                    'semantic' => ['max_files' => 30, 'max_file_size' => 200000],
                    'impact_analysis' => [
                        'max_symbols' => 40,
                        'max_files' => 32,
                        'max_file_size' => 100000,
                        'search_limit_per_symbol' => 100,
                        'min_relevance_score' => 0.26,
                    ],
                ],
                'xlarge' => [
                    'file_context' => ['max_files' => 18, 'max_file_size' => 90000],
                    'semantic' => ['max_files' => 28, 'max_file_size' => 180000],
                    'impact_analysis' => [
                        'max_symbols' => 36,
                        'max_files' => 28,
                        'max_file_size' => 90000,
                        'search_limit_per_symbol' => 90,
                        'min_relevance_score' => 0.28,
                    ],
                ],
            ],
        ],

        'indexing' => [
            'enabled' => (bool) env('REVIEW_ADAPTIVE_INDEXING_LIMITS', false),

            'change_volume_buckets' => [
                'small' => ['max_files' => 50],
                'medium' => ['max_files' => 200],
                'large' => ['max_files' => 600],
                'xlarge' => ['max_files' => 1000000],
            ],

            'tiers' => [
                'foundation' => [
                    'small' => ['full_reindex_threshold' => 500, 'batch_size' => 50],
                    'medium' => ['full_reindex_threshold' => 420, 'batch_size' => 40],
                    'large' => ['full_reindex_threshold' => 320, 'batch_size' => 32],
                    'xlarge' => ['full_reindex_threshold' => 260, 'batch_size' => 24],
                ],
                'illuminate' => [
                    'small' => ['full_reindex_threshold' => 600, 'batch_size' => 60],
                    'medium' => ['full_reindex_threshold' => 520, 'batch_size' => 52],
                    'large' => ['full_reindex_threshold' => 440, 'batch_size' => 44],
                    'xlarge' => ['full_reindex_threshold' => 360, 'batch_size' => 36],
                ],
                'orchestrate' => [
                    'small' => ['full_reindex_threshold' => 750, 'batch_size' => 75],
                    'medium' => ['full_reindex_threshold' => 650, 'batch_size' => 65],
                    'large' => ['full_reindex_threshold' => 550, 'batch_size' => 55],
                    'xlarge' => ['full_reindex_threshold' => 450, 'batch_size' => 45],
                ],
                'sanctum' => [
                    'small' => ['full_reindex_threshold' => 900, 'batch_size' => 90],
                    'medium' => ['full_reindex_threshold' => 780, 'batch_size' => 78],
                    'large' => ['full_reindex_threshold' => 660, 'batch_size' => 66],
                    'xlarge' => ['full_reindex_threshold' => 540, 'batch_size' => 54],
                ],
            ],
        ],
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
