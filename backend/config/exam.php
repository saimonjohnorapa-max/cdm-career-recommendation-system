<?php

return [
    // Empty means examination access is disabled until the campus network is configured.
    'allowed_networks' => array_values(array_filter(array_map('trim', explode(',', env('EXAM_ALLOWED_NETWORKS', ''))))),
    // Only these direct peers may supply the examination client's forwarded IP.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('EXAM_TRUSTED_PROXIES', ''))))),
];
