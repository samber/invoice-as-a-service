<?php

/*
|--------------------------------------------------------------------------
| Invoice
|--------------------------------------------------------------------------
|
| Instance-wide invoice settings.
|
| number_format: example of the number 1234.56 that defines how numbers are
| printed, such as "1,234.56", "1.234,56" or "1 234,56". The `number_format`
| request field wins over it. App\Helpers\NumberFormat validates it per request.
|
*/

return [
    'number_format' => env('INVOICE_NUMBER_FORMAT', '1,234.56'),
];
