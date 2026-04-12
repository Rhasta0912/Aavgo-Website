<?php

return [
    'client_id' => '1492874749288906803',
    'client_secret' => 'replace-with-your-rotated-discord-client-secret',
    'guild_id' => '1482220918355922974',
    'base_url' => 'https://www.aavgodesk.xyz',
    // Optional override if your server role IDs ever change.
    'role_ids' => [
        'admin' => [
            '1482732583660818636', // Team Leader
            '1482226842047090809', // Operations Manager
        ],
        'user' => [
            '1484705126026449029', // Trainee
            '1482227287159078964', // Agent
        ],
    ],
    // Optional admin user override for approved developers or owners.
    'admin_user_ids' => [
        '320128931971727360', // Alpha
        '1186978205018632242', // Astra
    ],
];
