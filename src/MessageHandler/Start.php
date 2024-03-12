<?php

namespace Webman\Midjourney\MessageHandler;

use Webman\Midjourney\Discord;
use Webman\Midjourney\Log;
use Webman\Midjourney\Task;
use Webman\Midjourney\TaskCondition;

class Start extends Base
{
    public static function handle($message): bool
    {
        $nonce = $message['d']['nonce'] ?? '';
        $messageType = $message['t'] ?? '';
        $messageId = $message['d']['id'] ?? '';
        if ($messageType === Discord::MESSAGE_CREATE && $nonce) {
            if (!$task = Discord::getRunningTaskByCondition((new TaskCondition())->nonce($nonce))) {
                Log::debug("MessageHandler Start no task found nonce=$nonce messageId=$messageId");
                return false;
            }
            $task->messageId($message['d']['id']);
            $task->status(Task::STATUS_RUNNING);
            if ($messageHash = Discord::getMessageHash($message)) {
                $task->messageHash($messageHash);
            }
            $task->buttons(Discord::getButtons($message));
            $task->save();
            Discord::notify($task);
            return true;
        }
        return false;
    }
}