<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Request Enforcement
    |--------------------------------------------------------------------------
    |
    | When enabled, all payments must go through the Payment Request workflow.
    | Direct PO/Invoice payments will be blocked and users will be redirected
    | to create a Payment Request first.
    |
    | Phase 1: Set to true to enforce strict payment request workflow
    | Phase 2: Remove legacy direct payment routes completely
    |
    */
    'enforce_request' => env('PAYMENTS_ENFORCE_REQUEST', true),
];
