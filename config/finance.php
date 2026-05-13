<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Finance Feature Flags
    |--------------------------------------------------------------------------
    |
    | Feature flags to control the rollout of new finance functionality.
    | Set to false to use legacy system, true to use new system.
    |
    */

    'po_locked_advance_enabled' => env('PO_LOCKED_ADVANCE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Financial Period Locking
    |--------------------------------------------------------------------------
    |
    | Enable financial period locking to prevent modifications to closed periods.
    | Set to false to disable period locking during initial rollout.
    |
    */

    'financial_period_locking_enabled' => env('FINANCIAL_PERIOD_LOCKING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Shadow Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the new system runs in parallel with the old system
    | for validation purposes without affecting actual operations.
    |
    */

    'shadow_mode_enabled' => env('SHADOW_MODE_ENABLED', false),
];
