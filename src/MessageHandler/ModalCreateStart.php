<?php

namespace Webman\Midjourney\MessageHandler;

use Webman\Midjourney\Discord;
use Webman\Midjourney\Log;
use Webman\Midjourney\Task;
use Webman\Midjourney\TaskCondition;

class ModalCreateStart extends Base
{
    public static function handle($message): bool
    {
        $nonce = $message['d']['nonce'] ?? '';
        $messageType = $message['t'] ?? '';
        $messageId = $message['d']['id'] ?? '';
        $modalActions = [
            Task::ACITON_ZOOMOUT_CUSTOM => 'MJ::OutpaintCustomZoomModal::prompt',
            Task::ACTION_PIC_READER => 'MJ::Picreader::Modal::PromptField',
        ];
        if ($messageType === Discord::INTERACTION_MODAL_CREATE && $nonce) {
            foreach ($modalActions as $action => $componentsCustomId) {
                if ($task = Discord::getRunningTaskByCondition((new TaskCondition())->action($action)->nonce($nonce))) {
                    $params = $task->params();
                    $params['messageId'] = $messageId;
                    $params['customId'] = $message['d']['custom_id'];
                    $params['componentsCustomId'] = $message['d']['components'][0]['components'][0]['custom_id'] ?? $componentsCustomId;
                    $params[Discord::INTERACTION_MODAL_CREATE] = true;
                    $task->params($params);
                    $task->nonce(Discord::uniqId());
                    $task->save();
                    $task->removeFromList(Discord::getRunningListName($task->discordId()));
                    Discord::execute($task);
                    return true;
                }
            }
        }
        return false;
    }
}