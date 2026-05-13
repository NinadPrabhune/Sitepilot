<?php

return [
    'scopes' => [
        'po' => 'workspace',
        'machinery_payment' => 'site',
        'indent' => 'site',
        'grn' => 'site',
        'invoice' => 'site',
        'payment' => 'site',
        'supplier_advance' => 'site',
        'material_issue' => 'site',
        'material_return' => 'site',
        'material_transfer' => 'site',
        'daily_consumption' => 'site',
    ],
    // Feature flag for high-load scenarios (500+ req/s)
    // Set to true to use atomic sequence table instead of scan + lock
    'use_sequence_table' => env('NUMBERING_USE_SEQUENCE_TABLE', false),
    // Lock latency threshold in seconds (circuit breaker)
    'lock_latency_threshold' => env('NUMBERING_LOCK_LATENCY_THRESHOLD', 1.0),
    // Reject requests when lock latency exceeds threshold
    'reject_on_high_latency' => env('NUMBERING_REJECT_ON_HIGH_LATENCY', true),
    // Queue number generation when lock latency exceeds threshold
    'queue_on_high_latency' => env('NUMBERING_QUEUE_ON_HIGH_LATENCY', false),
];
