<?php

use Webman\Midjourney\TaskStore\File;

return [
    'server' => [
        'handler' => Webman\Midjourney\Server::class,
        'listen' => 'http://0.0.0.0:8686',
        'reloadable' => false,
        'constructor' => [
            'config' => [
                'accounts' => [
                    [
                        'enable' => true,
                        'token' => '',
                        'guild_id' => '',
                        'channel_id' => '',
                        'useragent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
                        'concurrency' => 3, // 并发数
                        'timeoutMinutes' => 10, // 10分钟后超时
                    ]
                ],
                'proxy' => [
                    'server' => 'https://discord.com',
                    'cdn' => 'https://cdn.discordapp.com',
                    'gateway' => 'wss://gateway.discord.gg',
                    'upload' => 'https://discord-attachments-uploads-prd.storage.googleapis.com',
                ],
                'store' => [
                    'handler' => File::class,
                    'expiredDates' => 30, // 30天后过期
                    File::class => [
                        'dataPath' => runtime_path() . '/data/midjourney',
                    ]
                ],
                'settings' => [
                    'debug' => false,
                    'secret' => '',
                    'notifyUrl' => '',
                    'tmpPath' => runtime_path() . '/tmp/midjourney'
                ]
            ]
        ]
    ]
];
