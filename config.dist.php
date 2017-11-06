<?php
return [
    'accounts' => [
        'main' => [
            'key' => '/path/to/key',
        ],
    ],
    'domains' => [
        'example.com' => [
            'account' => 'main',
            'is_local' => false,
            'user' => 'nobody',
            'host' => 'example.com',
            'port' => 22,
            'auth_agent' => 'SSH_AUTH_SOCK',
            'auth_agent_sock' => '/path/to/sock',
            'auth_key' => '/path/to/key',
            'private' => '/usr/local/etc/cert/domain.key',
            'cert' => '/usr/local/etc/cert/domain.pem',
            'web_root' => '/var/www',
        ],
    ]
];
