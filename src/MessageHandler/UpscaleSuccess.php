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

class UpscaleSuccess extends Base
{
    protected static $regex = [
        '/\*\*(.*?)\*\* - Image #(\d) <@\d+>/'
    ];

    public static function handle($message): bool
    {
        $messageType = $message['t'] ?? '';
        [$finalPrompt, $index] = static::parseContents($message['d']['content'] ?? '');
        $referenceMessageId = $message['d']['message_reference']['message_id'] ?? '';
        $messageId = $message['d']['id'] ?? '';
        if ($messageType === Discord::MESSAGE_CREATE && Discord::hasImage($message) && $finalPrompt && $referenceMessageId) {
            $task = Discord::getRunningTaskByCondition((new TaskCondition())->action(Task::ACTION_UPSCALE)->params([
                'messageId' => $referenceMessageId,
                'index' => $index
            ]));
            if (!$task) {
                Log::debug("UpscaleSuccess no task found referenceMessageId={$referenceMessageId} index={$index} messageId={$messageId}");
                return false;
            }
            $imageUrl = $message['d']['attachments'][0]['url'] ?? '';
            $task->messageId($messageId);
            $task->imageUrl(Discord::replaceImageCdn($imageUrl));
            $task->imageRawUrl($imageUrl);
            $task->messageHash(Discord::getMessageHash($message));
            $task->finalPrompt($finalPrompt);
            $task->buttons(Discord::getButtons($message));
            $task->save();
            Discord::finished($task);
            return true;
        }
        return false;
    }

    public static function parseContents($content): array
    {
        if ($content === '') {
            return ['', 0];
        }
        foreach (static::$regex as $preg) {
            if (preg_match($preg, $content, $matches)) {
                return [$matches[1], (int)$matches[2]];
            }
        }
        return ['', 0];
    }

}