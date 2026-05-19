<?php

return [
    'base_url' => env('ACTIVECAMPAIGN_BASE_URL', ''),
    'api_key'  => env('ACTIVECAMPAIGN_API_KEY', ''),

    // cache (minutos) para ids de tags y campos
    'cache_ttl' => 60,

    // Async (queue) support
    'async'         => false,
    'queue'         => 'default',
    'async_tries'   => 3,
    'async_backoff' => [10, 60],
];
