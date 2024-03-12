<?php

namespace Webman\Midjourney\MessageHandler;

use Webman\Midjourney\Discord;
use Webman\Midjourney\Log;
use Webman\Midjourney\Task;
use Webman\Midjourney\TaskCondition;

class VaryRegionProgress extends Base
{
    public static function handle($message): bool
    {
        $messageType = $message['t'] ?? '';
        $interactionId = $message['d']['interaction_metadata']['id'] ?? '';
        $messageId = $message['d']['id'] ?? '';
        if ($messageType === Discord::MESSAGE_CREATE && $interactionId) {
            if (!$task = Discord::getRunningTaskByCondition((new TaskCondition())->action(Task::ACTION_VARIATION_REGION)->params([
                'interactionId' => $interactionId
            ]))) {
                Log::debug("VaryRegionProgress no task found interactionId=$interactionId messageId=$messageId");
                return false;
            }
            $task->messageId($messageId);
            if ($messageHash = Discord::getMessageHash($message)) {
                $task->messageHash($messageHash);
            }
            $task->buttons(Discord::getButtons($message));
            $task->save();
            return true;
        }
        return false;
    }
}