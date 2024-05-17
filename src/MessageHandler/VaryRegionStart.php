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

class VaryRegionStart extends Base
{
    public static function handle($message): bool
    {
        $nonce = $message['d']['nonce'] ?? '';
        $messageType = $message['t'] ?? '';
        $messageId = $message['d']['id'] ?? '';
        if ($messageType === Discord::INTERACTION_IFRAME_MODAL_CREATE && $nonce) {
            if (!$task = Discord::getRunningTaskByCondition((new TaskCondition())->nonce($nonce)->action(Task::ACTION_VARIATION_REGION))) {
                Log::debug("VaryRegionStart no task found nonce=$nonce messageId=$messageId");
                return false;
            }
            if ($task->params()[Discord::INTERACTION_IFRAME_MODAL_CREATE] ?? '') {
                Log::debug("VaryRegionStart already handled nonce=$nonce messageId=$messageId");
                return false;
            }
            if (!$customId = $message['d']['custom_id'] ?? '') {
                Log::debug("VaryRegionStart no custom_id nonce=$nonce messageId=$messageId");
                return false;
            }
            if (!$interactionId = $message['d']['id'] ?? '') {
                Log::debug("VaryRegionStart no interaction_id nonce=$nonce messageId=$messageId");
                return false;
            }
            $customId = substr($customId, strrpos($customId, ':') + 1);
            $params = $task->params();
            $params['customId'] = $customId;
            $params['interactionId'] = $interactionId;
            $params[Discord::INTERACTION_IFRAME_MODAL_CREATE] = true;
            $task->params($params);
            $task->nonce(Discord::uniqId());
            $task->buttons(Discord::getButtons($message));
            $task->save();
            $task->removeFromList(Discord::getRunningListName($task->discordId()));
            Discord::execute($task);
            return true;
        }
        return false;
    }
}