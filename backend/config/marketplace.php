<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kaamly Marketplace Business Configuration
    |--------------------------------------------------------------------------
    |
    | Centralized configuration settings for fee amounts, waiting periods,
    | debt restrictions, and operational parameters.
    |
    */

    // Authoritative COD Commission Debt Threshold (PKR)
    // Technicians with debt >= this limit cannot accept new jobs and are excluded from discovery
    'cod_debt_threshold' => (float) env('COD_DEBT_THRESHOLD', 2000.00),

    // Mandatory travel buffer added to service duration (minutes)
    'travel_buffer_minutes' => (int) env('TRAVEL_BUFFER_MINUTES', 30),

    // Standard Commission Rates & Fees
    'commission' => [
        'default_rate_percent' => (float) env('COMMISSION_DEFAULT_RATE', 10.00),
        'inspection_fee' => (float) env('INSPECTION_FEE_DEFAULT', 500.00),
    ],

    // Cancellation Policy Fees
    'cancellation_fees' => [
        'customer_post_dispatch' => (float) env('FEE_CUSTOMER_POST_DISPATCH', 500.00),
        'provider_unexcused' => (float) env('PENALTY_PROVIDER_UNEXCUSED', 300.00),
    ],

    // No-Show Attribution & Penalties
    'no_show' => [
        'customer_wait_threshold_minutes' => (int) env('NO_SHOW_CUSTOMER_WAIT_THRESHOLD_MINUTES', 20),
        'minimum_logged_calls' => (int) env('NO_SHOW_MIN_LOGGED_CALLS', 2),
        'provider_grace_period_minutes' => (int) env('NO_SHOW_PROVIDER_GRACE_PERIOD_MINUTES', 60),
        'customer_travel_compensation_fee' => (float) env('NO_SHOW_CUSTOMER_TRAVEL_FEE', 500.00),
        'provider_penalty_fee' => (float) env('NO_SHOW_PROVIDER_PENALTY_FEE', 500.00),
    ],

    // Inspection Quotation Parameters
    'quotation' => [
        'expiry_hours' => (int) env('QUOTATION_EXPIRY_HOURS', 24),
    ],

    // Formal Dispute Arbitration Parameters
    'dispute' => [
        'window_hours' => (int) env('DISPUTE_WINDOW_HOURS', 72),
    ],
];
