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