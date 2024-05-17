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

class Progress extends Base
{
    public static function handle($message): bool
    {
        $content = $message['d']['content'] ?? '';
        $messageType = $message['t'] ?? '';
        $finalPrompt = static::parseContent($content);
        $messageId = $message['d']['id'] ?? '';
        if ($messageType === Discord::MESSAGE_UPDATE && $finalPrompt) {
            if (!$task = Discord::getRunningTaskByCondition((new TaskCondition())->messageId($messageId))) {
                Log::debug("MessageHandler Progress no task found messageId={$messageId}");
                return false;
            }
            $task->status(Task::STATUS_RUNNING)->finalPrompt($finalPrompt);
            if (preg_match('/ \((\d+\%)\) \(.*?\)$/', $content, $matches)) {
                $task->progress($matches[1]);
            }
            $imageUrl = $message['d']['attachments'][0]['url'] ?? '';
            $task->imageUrl(Discord::replaceImageCdn($imageUrl));
            $task->imageRawUrl($imageUrl);
            $task->messageHash(Discord::getMessageHash($message));
            $task->buttons(Discord::getButtons($message));
            $task->save();
            Discord::notify($task);
            return true;
        }
        return false;
    }
}