<?php

namespace Webman\Midjourney\Service;

use Webman\Midjourney\Config;
use Webman\Midjourney\Discord;
use Webman\Midjourney\Task;
use Workerman\Http\Client;

class Image
{

    public static function imagine(Task $task, Discord $discord)
    {
        if (!$task->attachmentsReady()) {
            Attachment::upload($task);
            return;
        }
        $params = static::getParams($task, $discord);
        $client = new Client();
        $client->request(trim(Config::get('proxy.server'), ' /') . '/api/v9/interactions', static::payloadJson($task, $discord, $params));
    }

    protected static function payloadJson(Task $task, Discord $discord, array $params)
    {
        return [
            'method' => 'POST',
            'headers' => [
                'Authorization' => $discord->token()
            ],
            'data' => [
                'payload_json' => json_encode($params)
            ],
            'success' => function ($response) use ($task, $discord) {
                $content = (string)$response->getBody();
                $json = $content ? json_decode($content, true) : null;
                if ($content === '' || $json['retry_after'] ?? 0) {
                    $task->status(Task::STATUS_SUBMITTED)->save();
                    Discord::notify($task);
                    return;
                }
                Discord::failed($task, $content);
            },
            'error' => function ($error) use ($task, $discord) {
                Discord::failed($task, $error->getMessage());
            }
        ];
    }

    protected static function json(Task $task, Discord $discord, array $params)
    {
        return [
            'method' => 'POST',
            'headers' => [
                'Authorization' => $discord->token(),
                'Content-Type' => 'application/json'
            ],
            'data' => json_encode($params),
            'success' => function ($response) use ($task, $discord) {
                $content = (string)$response->getBody();
                $json = $content ? json_decode($content, true) : null;
                if ($content === '' || $json['retry_after'] ?? 0) {
                    $task->status(Task::STATUS_SUBMITTED)->save();
                    Discord::notify($task);
                    return;
                }
                Discord::failed($task, $content);
            },
            'error' => function ($error) use ($task, $discord) {
                Discord::failed($task, $error->getMessage());
            }
        ];
    }

    public static function change(Task $task, Discord $discord)
    {
        $params = static::getParams($task, $discord);
        $client = new Client();
        $client->request(trim(Config::get('proxy.server'), ' /') . '/api/v9/interactions', static::json($task, $discord, $params));
    }

    public static function describe(Task $task, Discord $discord)
    {
        if (!$task->attachmentsReady()) {
            Attachment::upload($task);
            return;
        }
        $params = static::getParams($task, $discord);
        $client = new Client();
        $client->request(trim(Config::get('proxy.server'), ' /') . '/api/v9/interactions', static::payloadJson($task, $discord, $params));
    }

    public static function blend(Task $task, Discord $discord)
    {
        if (!$task->attachmentsReady()) {
            Attachment::upload($task);
            return;
        }
        $params = static::getParams($task, $discord);
        $client = new Client();
        $client->request(trim(Config::get('proxy.server'), ' /') . '/api/v9/interactions', static::payloadJson($task, $discord, $params));
    }

    public static function varyRegion(Task $task, Discord $discord)
    {
        $params = static::getParams($task, $discord);
        $client = new Client();
        if (!($task->params()[Discord::INTERACTION_IFRAME_MODAL_CREATE] ?? '')) {
            $url = trim(Config::get('proxy.server'), ' /') . '/api/v9/interactions';
            $client->request($url, static::payloadJson($task, $discord, $params));
            return;
        }
        $url = 'https://' . DisCord::APPLICATION_ID . '.discordsays.com/inpaint/api/submit-job';
        $client->request($url, [
            'method' => 'POST',
            'headers' => [
                'Authorization' => $discord->token(),
                'Content-Type' => 'application/json'
            ],
            'data' => json_encode($params),
            'success' => function ($response) use ($task, $discord) {
                $content = (string)$response->getBody();
                if (!strpos(strtolower($content), 'success')) {
                    Discord::failed($task, $content);
                }
            },
            'error' => function ($error) use ($task, $discord) {
                Discord::failed($task, $error->getMessage());
            }
        ]);
    }

    public static function cancelJob(Task $task, Discord $discord)
    {
        $params = static::getParams($task, $discord);
        $client = new Client();
        $client->request(trim(Config::get('proxy.server'), ' /') . '/api/v9/interactions', [
            'method' => 'POST',
            'headers' => [
                'Authorization' => $discord->token(),
                'Content-Type' => 'application/json'
            ],
            'data' => json_encode($params),
            'success' => function ($response) use ($task, $discord) {
                $content = (string)$response->getBody();
                $json = $content ? json_decode($content, true) : null;
                if ($content === '' || $json['retry_after'] ?? 0) {
                    Discord::finished($task);
                    return;
                }
                Discord::failed($task, $content);
            },
            'error' => function ($error) use ($task, $discord) {
                Discord::failed($task, $error->getMessage());
            }
        ]);
    }

    public static function getParams(Task $task, Discord $discord)
    {
        switch ($task->action()) {
            case Task::ACTION_IMAGINE:
                return [
                    'type' => 2,
                    'guild_id' => $discord->guildId(),
                    'channel_id' => $discord->channelId(),
                    'application_id' => Discord::APPLICATION_ID,
                    'session_id' => $discord->sessionId(),
                    'nonce' => $task->nonce(),
                    'data' => [
                        'version' => Discord::IMAGINE_COMMAND_VERSION,
                        'id' => Discord::IMAGINE_COMMAND_ID,
                        'name' => 'imagine',
                        'type' => 1,
                        'options' => [[
                            'type' => 3,
                            'name' => 'prompt',
                            'value' => $task->prompt(),
                        ]],
                    ],
                ];
            case Task::ACTION_DESCRIBE:
                return [
                    'type' => 2,
                    'guild_id' => $discord->guildId(),
                    'channel_id' => $discord->channelId(),
                    'application_id' => Discord::APPLICATION_ID,
                    'session_id' => $discord->sessionId(),
                    'nonce' => $task->nonce(),
                    'data' => [
                        'version' => Discord::DESCRIBE_COMMAND_VERSION,
                        'id' => Discord::DESCRIBE_COMMAND_ID,
                        'name' => 'describe',
                        'type' => 1,
                        'options' => [[
                            'type' => 11,
                            'name' => 'image',
                            'value' => 0,
                        ]],
                        'attachments' => [[
                            'id' => "0",
                            'filename' => $task->attachments()[0]['filename'],
                            'uploaded_filename' => $task->attachments()[0]['upload_filename'],
                        ]],
                    ],
                ];
            case Task::ACTION_BLEND:
                $params = [
                    'type' => 2,
                    'guild_id' => $discord->guildId(),
                    'channel_id' => $discord->channelId(),
                    'application_id' => Discord::APPLICATION_ID,
                    'session_id' => $discord->sessionId(),
                    'nonce' => $task->nonce(),
                    'data' => [
                        'version' => Discord::BLEND_COMMAND_VERSION,
                        'id' => Discord::BLEND_COMMAND_ID,
                        'name' => 'blend',
                        'type' => 1,
                        'options' => [
                        ],
                        'attachments' => [
                        ],
                    ],
                ];
                foreach ($task->attachments() as $index => $attachment) {
                    $params['data']['options'][] = [
                        'type' => 11,
                        'name' => "image" . ($index + 1),
                        'value' => $index,
                    ];
                    $params['data']['attachments'][] = [
                        'id' => "$index",
                        'filename' => $attachment['filename'],
                        'uploaded_filename' => $attachment['upload_filename'],
                    ];
                }
                if ($task->params()['dimensions'] ?? '') {
                    $params['data']['options'][] = [
                        'type' => 3,
                        'name' => 'dimensions',
                        'value' => $task->params()['dimensions'],
                    ];
                }
                return $params;
            case Task::ACITON_ZOOMOUT_CUSTOM:
            case TAsk::ACTION_PIC_READER:
                if (!($task->params()[Discord::INTERACTION_MODAL_CREATE] ?? false)) {
                    return [
                        'type' => 3,
                        'message_id' => $task->params()['messageId'],
                        'application_id' => Discord::APPLICATION_ID,
                        'channel_id' => $discord->channelId(),
                        'guild_id' => $discord->guildId(),
                        'message_flags' => 0,
                        'session_id' => $discord->sessionId(),
                        'nonce' => $task->nonce(),
                        'data' => [
                            'component_type' => 2,
                            'custom_id' => $task->params()['customId'],
                        ],
                    ];
                }
                return [
                    'type' => 5,
                    'application_id' => Discord::APPLICATION_ID,
                    'channel_id' => $discord->channelId(),
                    'guild_id' => $discord->guildId(),
                    'data' => [
                        'id' => $task->params()['messageId'],
                        'custom_id' => $task->params()['customId'],
                        'components' => [[
                            'type' => 1,
                            'components' => [[
                                'type' => 4,
                                'custom_id' => $task->params()['componentsCustomId'],
                                //'value' => $task->params()['prompt'],
                                'value' => $task->prompt(),
                            ]]
                        ]]
                    ],
                    'session_id' => $discord->sessionId(),
                    'nonce' => $task->nonce(),
                ];
            case Task::ACTION_VARIATION_REGION:
                if ($task->params()[Discord::INTERACTION_IFRAME_MODAL_CREATE] ?? '') {
                    return [
                        'username' => '0',
                        'userId' => '0',
                        'customId' => $task->params()['customId'],
                        'mask' => $task->params()['mask'],
                        'prompt' => $task->prompt(),
                        'full_prompt' => null,
                    ];
                }
                return [
                    'type' => 3,
                    'guild_id' => $discord->guildId(),
                    'channel_id' => $discord->channelId(),
                    'message_id' => $task->params()['messageId'],
                    'application_id' => Discord::APPLICATION_ID,
                    'session_id' => $discord->sessionId(),
                    'nonce' => $task->nonce(),
                    'message_flags' => 0,
                    'data' => [
                        'component_type' => 2,
                        'custom_id' => $task->params()['customId'],
                    ],
                ];
            case Task::ACTION_CANCEL_JOB:
                return [
                    'type' => 3,
                    'guild_id' => $discord->guildId(),
                    'channel_id' => $discord->channelId(),
                    'message_id' => $task->params()['messageId'],
                    'application_id' => Discord::APPLICATION_ID,
                    'session_id' => $discord->sessionId(),
                    'nonce' => $task->nonce(),
                    'message_flags' => 64,
                    'data' => [
                        'component_type' => 2,
                        'custom_id' => $task->params()['customId'],
                    ],
                ];
            default:
                return [
                    'type' => 3,
                    'guild_id' => $discord->guildId(),
                    'channel_id' => $discord->channelId(),
                    'message_id' => $task->params()['messageId'],
                    'application_id' => Discord::APPLICATION_ID,
                    'session_id' => $discord->sessionId(),
                    'nonce' => $task->nonce(),
                    'message_flags' => 0,
                    'data' => [
                        'component_type' => 2,
                        'custom_id' => $task->params()['customId'],
                    ],
                ];
        }
    }

}