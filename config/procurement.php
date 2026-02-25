<?php

return [
    // Number of attempts to retry automatic post of PurchaseReceipt after QC
    'auto_post_retries' => env('PROCUREMENT_AUTO_POST_RETRIES', 3),

    // Backoff delays (in milliseconds) used between retry attempts.
    'auto_post_backoff_ms' => array_map('intval', explode(',', env('PROCUREMENT_AUTO_POST_BACKOFF_MS', '200,500,1000'))),

    // Automatically create a Purchase Invoice when a PurchaseReceipt is fully completed.
    // Set to true to enable auto-invoice creation, false to require manual creation.
    'auto_create_invoice' => env('PROCUREMENT_AUTO_CREATE_INVOICE', false),
];
