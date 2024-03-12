<?php

namespace Webman\Midjourney\MessageHandler;

use Webman\Midjourney\Discord;
use Webman\Midjourney\Log;
use Webman\Midjourney\Task;
use Webman\Midjourney\TaskCondition;

class DescribeSuccess extends Base
{
    public static function handle($message): bool
    {
        $messageType = $message['t'] ?? '';
        $description = $message['d']['embeds'][0]['description'] ?? '';
        $interactionName = $message['d']['interaction']['name'] ?? '';
        $messageId = $message['d']['id'] ?? '';
        if ($messageType === Discord::MESSAGE_UPDATE && $interactionName === 'describe' && $description) {
            if (!$task = Discord::getRunningTaskByCondition((new TaskCondition())->messageId($messageId)->action(Task::ACTION_DESCRIBE))) {
                Log::debug("MessageHandler DescribeSuccess no task found messageId={$messageId}");
                return false;
            }
            $task->description($description);
            $task->buttons(Discord::getButtons($message));
            $task->save();
            Discord::finished($task);
            return true;
        }
        return false;
    }

}