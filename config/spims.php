<?php

return [
    'setup_token' => env('SPIMS_SETUP_TOKEN'),
    'image_max_kb' => (int) env('SPIMS_IMAGE_MAX_KB', 4096),
    'health_token' => env('SPIMS_HEALTH_TOKEN'),
    'currencies' => ['AED', 'PKR', 'USD', 'EUR', 'GBP', 'CNY'],
    'per_page' => 25,
];
