<?php

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
        if ($messageType === Discord::MESSAGE_CREATE && !$nonce && $finalPrompt && $messageHash) {
            if (!$task = Discord::getRunningTaskByCondition((new TaskCondition())->messageHash($messageHash))) {
                Log::debug("MessageHandler Success no task found messageHash=$messageHash messageId=$messageId");
                return false;
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