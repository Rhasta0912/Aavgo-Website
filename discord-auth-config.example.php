<?php

return [
    'client_id' => '1492874749288906803',
    'client_secret' => 'replace-with-your-rotated-discord-client-secret',
    'guild_id' => '1482220918355922974',
    'base_url' => 'https://www.aavgodesk.xyz',
    'website_api_token' => 'replace-with-your-shared-hours-sync-token',
    // Optional local override if you want the snapshot stored somewhere else.
    'hours_snapshot_path' => '/home/aavgodes/admin-hours-snapshot.json',
    // Optional legacy fallback if you still expose the bot bridge over HTTPS.
    'website_api_url' => 'https://your-bot-api-host.example.com',
    // Optional override if your server role IDs ever change.
    'role_ids' => [
        'admin' => [
            '1482312134875418737', // Developer
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
