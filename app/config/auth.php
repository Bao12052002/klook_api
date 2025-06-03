<?php
define('KLOOK_API_TOKENS', [
    '0e466a9a1c9f6d4ea8fe5f69a6c5b2c5' => [
        'name' => 'Main API Token',
        'capabilities' => ['read', 'write', 'delete']
    ],
    'read_only_token' => [
        'name' => 'Read Only Token', 
        'capabilities' => ['read']
    ]
]);

define('KLOOK_SECRET_KEY', '0e466a9a1c9f6d4ea8fe5f69a6c5b2c5');
