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