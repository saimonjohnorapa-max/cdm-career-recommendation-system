<?php

return [
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'portal_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'campus_auth_per_minute' => (int) env('CAMPUS_AUTH_PER_MINUTE', 2000),
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'ca_bundle' => env('GOOGLE_CA_BUNDLE', PHP_OS_FAMILY === 'Windows' && is_file(storage_path('app/private/local-ca-bundle.pem')) ? storage_path('app/private/local-ca-bundle.pem') : null),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'allowed_domain' => env('GOOGLE_ALLOWED_DOMAIN', 'student.pnm.edu.ph'),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
    ],
];
