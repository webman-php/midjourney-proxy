<?php

namespace Webman\Midjourney\MessageHandler;

use Webman\Midjourney\Discord;
use Webman\Midjourney\Task;
use Webman\Midjourney\TaskCondition;

class InteractionFailure extends Base
{
    public static function handle($message): bool
    {
        $nonce = $message['d']['nonce'] ?? '';
        $messageType = $message['t'] ?? '';
        if ($messageType === Discord::INTERACTION_FAILURE && $nonce) {
            $task = Discord::getRunningTaskByCondition((new TaskCondition())->nonce($nonce));
            $params = $task->params();
            $params[Discord::INTERACTION_FAILURE] = true;
            $task->params($params);
            $task->save();
        }
        return false;
    }
}