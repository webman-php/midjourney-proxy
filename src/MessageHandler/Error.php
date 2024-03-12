<?php

namespace Webman\Midjourney\MessageHandler;

use Webman\Midjourney\Discord;
use Webman\Midjourney\Log;
use Webman\Midjourney\TaskCondition;

class Error extends Base
{

    public static function handle($message): bool
    {
        $messageType = $message['t'] ?? '';
        $content = $message['d']['content'] ?? '';
        $nonce = $message['d']['nonce'] ?? '';
        if (strpos($content, 'Failed to process your command') === 0 && $task = Discord::getRunningTaskByCondition((new TaskCondition())->nonce($nonce))) {
            Discord::failed($task, $content);
            return true;
        }
        if ($messageType === Discord::MESSAGE_CREATE && ($title = $message['d']['embeds'][0]['title'] ?? '')) {
            $color = $message['d']['embeds'][0]['color'] ?? 0;
            $description = $message['d']['embeds'][0]['description'] ?? '';
            $referenceMessageId = $message['d']['message_reference']['message_id'] ?? '';
            $errorContent = "[ $title ] $description";
            if ($color === 16711680 && $nonce && $task = Discord::getRunningTaskByCondition((new TaskCondition())->nonce($nonce))) {
                Discord::failed($task, $errorContent);
                return true;
            } else if(strpos(strtolower($title), 'invalid link')) {
                if ($task = Discord::getRunningTaskByCondition((new TaskCondition())->params(['messageId' => $referenceMessageId]))) {
                    Discord::failed($task, $errorContent);
                    return true;
                }
            } else if ($color === 16239475) {
                Log::info($errorContent);
                return true;
            }
        }
        return false;
    }

}