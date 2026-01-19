<?php

declare(strict_types=1);

/**
 * Briefings feature configuration.
 *
 * Controls limits, retention, storage, and scheduling for the Briefings feature.
 *
 * @see App\Models\Briefing
 * @see App\Services\Briefings\BriefingLimitEnforcer
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Generation Limits
    |--------------------------------------------------------------------------
    |
    | These limits control the maximum parameters allowed when generating
    | briefings. They prevent excessive resource usage and protect system
    | performance.
    |
    */
    'limits' => [
        // Maximum number of days allowed in a date range parameter
        'max_date_range_days' => (int) env('BRIEFINGS_MAX_DATE_RANGE', 90),

        // Maximum number of repositories allowed per briefing generation
        'max_repositories' => (int) env('BRIEFINGS_MAX_REPOSITORIES', 10),

        // Maximum concurrent generations per workspace
        'max_concurrent_generations' => (int) env('BRIEFINGS_MAX_CONCURRENT', 3),

        // Timeout for a single generation in seconds
        'generation_timeout_seconds' => (int) env('BRIEFINGS_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Settings
    |--------------------------------------------------------------------------
    |
    | Configure how long generated briefings and share links are retained
    | before being cleaned up.
    |
    */
    'retention' => [
        // Number of days to retain completed generations before cleanup
        'generations_days' => (int) env('BRIEFINGS_RETENTION_DAYS', 90),

        // Default expiry in days for new share links
        'shares_default_expiry_days' => (int) env('BRIEFINGS_SHARE_EXPIRY', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the storage disk and path for generated briefing files
    | (PDFs, HTML exports, etc.). Uses Cloudflare R2 by default.
    |
    */
    'storage' => [
        // Storage disk for generated files (should be S3-compatible like R2, Spaces, etc.)
        'disk' => env('BRIEFINGS_DISK', 's3'),

        // Base path within the disk
        'path' => 'briefings',

        // Temporary URL expiry in minutes (for private file access)
        'temporary_url_expiry_minutes' => (int) env('BRIEFINGS_TEMP_URL_EXPIRY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling Defaults
    |--------------------------------------------------------------------------
    |
    | Default schedule settings for subscription presets.
    |
    */
    'scheduling' => [
        'presets' => [
            'daily' => [
                'hour' => 8, // 8 AM UTC
            ],
            'weekly' => [
                'day' => 1,   // Monday
                'hour' => 9,  // 9 AM UTC
            ],
            'monthly' => [
                'day' => 1,   // 1st of month
                'hour' => 9,  // 9 AM UTC
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Generation
    |--------------------------------------------------------------------------
    |
    | Configure the PDF generation driver and settings.
    |
    */
    'pdf' => [
        // Driver for PDF generation (browsershot, dompdf, etc.)
        'driver' => env('BRIEFINGS_PDF_DRIVER', 'browsershot'),

        // Path to Chrome/Chromium binary (for browsershot)
        'chrome_path' => env('BRIEFINGS_CHROME_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for AI-powered narrative generation.
    |
    */
    'ai' => [
        // Default AI provider for narrative generation
        'provider' => env('BRIEFINGS_AI_PROVIDER', 'anthropic'),

        // Default model for narrative generation
        'model' => env('BRIEFINGS_AI_MODEL', 'claude-sonnet-4-20250514'),

        // Maximum tokens for narrative generation
        'max_tokens' => (int) env('BRIEFINGS_AI_MAX_TOKENS', 2000),
    ],
];
