<?php

/**
 * Path: config/pricing.php
 *
 * Pricing process logging + workflow knobs.
 * Toggle verbose forensic logs with PRICING_PROCESS_LOG=true in .env
 */

return [

    'process_log' => (bool) env('PRICING_PROCESS_LOG', true),

    'process_log_dir' => storage_path('logs/pricing'),

    'progress_cache_ttl_hours' => 6,

    'detect_chunk' => 200,

    'price_import_chunk' => 100,

    'job_timeout' => 1800,

    'abandoned_session_days' => 7,

    'tcs_limit' => (float) env('PRICING_TCS_LIMIT', 1000000),
    'tcs_rate'  => (float) env('PRICING_TCS_RATE', 1),
    'calc_batch_chunk' => 100,

    /*
     | Price List sheet title token → segment.code
     | First token after "PRICE LIST" in the sheet name.
     */
    'sheet_segment_map' => [
        'PV'  => 'PV',
        'CV'  => 'CV',
        'BEV' => 'BEV',
        'LMM' => 'LMM',
        'TZU' => 'LMM',
        'CSD' => 'CSD',
        'TAXI' => 'PV',
    ],

    'header_aliases' => [
        'oem_code'    => ['oem code', 'model code', 'vehicle code', 'oemcode'],
        'oem_model'   => ['oem model', 'oemmodel', 'model'],
        'oem_variant' => ['oem variant', 'oemvariant', 'variant'],
    ],
];
