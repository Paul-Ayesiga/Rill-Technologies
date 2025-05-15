<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    |
    | These settings are used when generating invoice PDFs.
    |
    */

    'street' => env('INVOICE_STREET', 'Main Street 1'),
    'location' => env('INVOICE_LOCATION', '2000 Antwerp, Belgium'),
    'phone' => env('INVOICE_PHONE', '+32 499 00 00 00'),
    'email' => env('INVOICE_EMAIL', 'info@example.com'),
    'vat' => env('INVOICE_VAT', 'BE123456789'),
];
