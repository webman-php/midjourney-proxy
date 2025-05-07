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

namespace Webman\Midjourney\MessageHandler;

use Webman\Midjourney\Discord;
use Webman\Midjourney\Log;
use Webman\Midjourney\Task;
use Webman\Midjourney\TaskCondition;

class Success extends Base
{

    public static function handle($message): bool
    {
        $nonce = $message['d']['nonce'] ?? '';
        $messageType = $message['t'] ?? '';
        $messageHash = Discord::getMessageHash($message);
        $finalPrompt = static::parseContent($message['d']['content'] ?? '');
        $messageId = $message['d']['id'] ?? '';
        if ($messageType === Discord::MESSAGE_CREATE && $finalPrompt && $messageHash) {
            if (!$task = Discord::getRunningTaskByCondition((new TaskCondition())->messageHash($messageHash))) {
                Log::debug("MessageHandler Success no task found messageHash=$messageHash messageId=$messageId nonce=$nonce and try to find InteractionFailure task");
                $task = Discord::getRunningTaskByCondition((new TaskCondition())->prompt($finalPrompt)->params([Discord::INTERACTION_FAILURE => true]));
                if (!$task) {
                    Log::debug("MessageHandler Success no task found messageHash=$messageHash messageId=$messageId nonce=$nonce and no InteractionFailure task found");
                    $task = Discord::getRunningTaskByCondition((new TaskCondition())->prompt($finalPrompt)) ?: Discord::getRunningTaskByCondition((new TaskCondition())->finalPrompt($finalPrompt));
                    if (!$task) {
                        Log::debug("MessageHandler Success no task found messageHash=$messageHash messageId=$messageId nonce=$nonce prompt=$finalPrompt and no task found");
                        return false;
                    }
                }
            }
            $imageUrl = $message['d']['attachments'][0]['url'] ?? '';
            $task->messageId($messageId);
            $task->imageUrl(Discord::replaceImageCdn($imageUrl));
            $task->imageRawUrl($imageUrl);
            $task->finalPrompt($finalPrompt);
            if (!$task->prompt()) {
                $task->prompt($finalPrompt);
            }
            $task->buttons(Discord::getButtons($message));
            $task->save();
            Discord::finished($task);
            return true;
        }
        return false;
    }

}
