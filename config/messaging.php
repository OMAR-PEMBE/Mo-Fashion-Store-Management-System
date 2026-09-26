<?php

/*
| Customer messaging (WhatsApp receipts now; SMS campaigns and the WhatsApp bot later).
| Every channel has a "log" driver that writes messages to the application log instead of
| sending them, so the whole flow can be built and tested without accounts or cost.
| Going live is a matter of setting the driver and credentials in .env.
*/

return [

    'whatsapp' => [
        // log | cloud (Meta WhatsApp Cloud API)
        'driver' => env('WHATSAPP_DRIVER', 'log'),

        'cloud' => [
            'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),
            'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
        ],

        // The approved "utility" template used for receipts. Its body must take three values,
        // in this order: customer name, receipt text (number and total), receipt link.
        'receipt_template' => env('WHATSAPP_RECEIPT_TEMPLATE', 'sale_receipt'),
        'receipt_language' => env('WHATSAPP_RECEIPT_LANGUAGE', 'en'),
    ],

    'sms' => [
        // log (a real provider is added when one is chosen)
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    // How long a receipt link sent to a customer keeps working.
    'receipt_link_days' => (int) env('RECEIPT_LINK_DAYS', 90),

    // Failed sends are retried after these many seconds, then marked failed.
    'retry_backoff' => [60, 300, 900],

];
