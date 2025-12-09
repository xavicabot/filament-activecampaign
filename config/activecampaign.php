<?php

return [
    'base_url' => env('ACTIVECAMPAIGN_BASE_URL', ''),
    'api_key'  => env('ACTIVECAMPAIGN_API_KEY', ''),

    // cache (minutos) para ids de tags y campos
    'cache_ttl' => 60,
];
