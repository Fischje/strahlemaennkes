<?php
return [
    'app_name' => 'Strahlemännkes',
    'base_url' => rtrim(getenv('APP_URL') ?: '', '/'),
    'db' => [
        // Eine einzelne lokale SQLite-Datei. Kann bei Bedarf per SQLITE_PATH verschoben werden.
        'path' => getenv('SQLITE_PATH') ?: __DIR__ . '/data/strahlemaennkes.sqlite',
    ],
    'push' => [
        'subject' => getenv('VAPID_SUBJECT') ?: 'mailto:admin@example.invalid',
        'public_key' => getenv('VAPID_PUBLIC_KEY') ?: '',
        'private_key' => getenv('VAPID_PRIVATE_KEY') ?: '',
    ],
];
