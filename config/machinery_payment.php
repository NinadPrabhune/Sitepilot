<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Machinery Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for machinery payment integration and safety controls
    |
    */
    
    'write_enabled' => env('MACHINERY_PAYMENT_WRITE_ENABLED', true), 
    'enable_erp_payment_button' => env('MACHINERY_PAYMENT_ERP_BUTTON_ENABLED', true),
    'test_mode' => env('MACHINERY_PAYMENT_TEST_MODE', true),
];
