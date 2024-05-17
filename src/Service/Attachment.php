<?php

/**
 * This file is part of webman/midjourney-proxy.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Webman\Midjourney\Service;

use Throwable;
use Webman\Midjourney\Config;
use Webman\Midjourney\Discord;
use Webman\Midjourney\Log;
use Webman\Midjourney\Task;
use Workerman\Http\Client;
use Workerman\Http\Response;

class Attachment
{

    /**
     * @var Client
     */
    protected static $httpClient;

    /**
     * @param Task $task
     * @return void
     */
    public static function upload(Task $task)
    {
        static::tryInitHttpClient();
        foreach ($task->images() as $index => $url) {
            Log::debug("TASK:{$task->id()} Attachment download from $url");
            static::$httpClient->get($url, function (Response $response) use ($task, $url, $index) {
                $content = (string)$response->getBody();
                $statusCode = $response->getStatusCode();
                if ($statusCode !== 200) {
                    Discord::failed($task, "TASK:{$task->id()} Failed to download image from $url statusCode:$statusCode body:$content");
                    return;
                }
                $filename = md5($url);
                $path = Config::get('settings.tmpPath');
                if (!$path) {
                    Discord::failed($task, "TASK:{$task->id()} tmpPath not found");
                    return;
                }
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }
                $path .= DIRECTORY_SEPARATOR . $filename;
                Log::debug("TASK:{$task->id()} Save image from $url to $path");
                $ret = file_put_contents($path, $response->getBody());
                if ($ret === false) {
                    Discord::failed($task, "Failed to save image from $url to $path");
                    return;
                }
                if (!getimagesize($path)) {
                    Discord::failed($task, "Invalid image from $url");
                    static::unlink($path);
                }
                $discord = Discord::get($task->discordId());
                if (!$discord) {
                    Discord::failed($task, "Discord instance(" . $task->discordId() . ") not found");
                    static::unlink($path);
                    return null;
                }
                $uploadUrl = Discord::getServer() . "/api/v9/channels/" . $discord->channelId() . "/attachments";
                Log::debug("TASK:{$task->id()} send attachments info to $uploadUrl");
                static::$httpClient->request($uploadUrl, [
                    'method' => 'POST',
                    'data' => json_encode(['files' => [[
                        'filename' => 'image.png',
                        'file_size' => filesize($path),
                        'id' => 0,
                        'is_clip' => false
                    ]]]),
                    'headers' => [
                        'Authorization' => Discord::get($task->discordId())->token(),
                        'User-Agent' => $discord->userAgent(),
                        'Content-Type' => 'application/json'
                    ],
                    'success' => function (Response $response) use ($task, $path, $index, $discord, $url, $uploadUrl) {
                        $content = (string)$response->getBody();
                        $result = json_decode($content, true);
                        if (!$putUrl = $result['attachments'][0]['upload_url'] ?? '') {
                            Discord::failed($task, "Failed to send attachments info $uploadUrl, invalid response $content");
                            static::unlink($path);
                            return;
                        }
                        $putUrl = Discord::replaceUploadUrl($putUrl);
                        Log::debug("TASK:{$task->id()} Try upload image to $putUrl");
                        static::$httpClient->request($putUrl, [
                            'method' => 'PUT',
                            'data' => file_get_contents($path),
                            'headers' => [
                                'User-Agent' => $discord->userAgent(),
                                'Content-Type' => 'application/octet-stream',
                            ],
                            'success' => function (Response $response) use ($task, $path, $index, $result, $putUrl, $discord, $url) {
                                if ($response->getStatusCode() !== 200) {
                                    Discord::failed($task, "Failed to upload image to $putUrl " . $response->getBody());
                                    static::unlink($path);
                                    return;
                                }
                                $data = [
                                    'url' => $url,
                                    'filename' => basename($result['attachments'][0]['upload_filename']),
                                    'upload_filename' => $result['attachments'][0]['upload_filename'],
                                ];
                                $task->addAttachment($index, $data);
                                $task->save();
                                if ($task->attachmentsReady()) {
                                    if ($task->action() === Task::ACTION_IMAGINE) {
                                        static::sendAttachmentMessage($task, $discord, function () use ($task) {
                                            $prompt = $task->prompt();
                                            foreach (array_reverse($task->attachments()) as $attachment) {
                                                $prompt = "<{$attachment['cdn_url']}> " . $prompt;
                                            }
                                            $task->prompt($prompt);
                                            Log::info("TASK:{$task->id()} Send attachments message ready and execute");
                                            Discord::execute($task);
                                        });
                                    } else {
                                        Log::info("TASK:{$task->id()} Attachments ready and execute");
                                        Discord::execute($task);
                                    }
                                }
                                static::unlink($path);
                            },
                            'error' => function (Throwable $e) use ($task, $path, $putUrl) {
                                Discord::failed($task, "Failed to upload image to $putUrl " . $e->getMessage());
                                static::unlink($path);
                            }
                        ]);
                        static::unlink($path);
                    },
                    'error' => function (Throwable $e) use ($task, $path) {
                        Discord::failed($task, "Failed to upload image from $path " . $e->getMessage());
                        static::unlink($path);
                    }
                ]);
            }, function(Throwable $e) use ($task, $url) {
                Discord::failed($task, "Failed to download image from $url " . $e->getMessage());
            });
        }
    }

    protected static function unlink($path)
    {
        try {
            if (file_exists($path)) {
                unlink($path);
            }
        } catch (Throwable $e) {}
    }

    protected static function tryInitHttpClient()
    {
        static::$httpClient = new Client([
            'max_conn_per_addr' => 8,
            'timeout' => 120
        ]);
    }

    public static function sendAttachmentMessage(Task $task, Discord $discord, callable $cb)
    {
        $attachments = [];
        foreach ($task->attachments() as $index => $attachment) {
            $attachments[] = [
                'id' => (string)$index,
                'filename' => $attachment['filename'],
                'uploaded_filename' => $attachment['upload_filename']
            ];
        }
        $url = Discord::getServer() . '/api/v9/channels/' . $discord->channelId() . '/messages';
        Log::debug("TASK:{$task->id()} Send attachment message to $url");
        static::$httpClient->request($url, [
            'method' => 'POST',
            'headers' => [
                'Authorization' => $discord->token(),
                'Content-Type' => 'application/json'
            ],
            'data' => json_encode([
                'content' => '',
                'nonce' => Discord::uniqId(),
                'channel_id' => $discord->channelId(),
                'type' => 0,
                'sticker_ids' => [],
                'attachments' => $attachments
            ]),
            'success' => function ($response) use ($task, $discord, $cb) {
                $content = (string)$response->getBody();
                $json = $content ? json_decode($content, true) : null;
                if (!$responseAttachments = $json['attachments'] ?? null) {
                    Discord::failed($task, "Failed to send attachment message, invalid response $content");
                    return;
                }
                try {
                    $attachments = $task->attachments();
                    foreach ($responseAttachments as $index => $responseAttachment) {
                        $attachments[$index]['cdn_url'] = $responseAttachment['url'];
                    }
                    $task->attachments($attachments);
                    $task->save();
                    Log::debug("TASK:{$task->id()} Send attachment message success and try call cb");
                    $cb();
                } catch (Throwable $e) {
                    Discord::failed($task, "Failed to send attachment message, " . $e->getMessage());
                }
            },
            'error' => function ($error) use ($task, $discord) {
                Discord::failed($task, $error->getMessage());
            }
        ]);
    }

}