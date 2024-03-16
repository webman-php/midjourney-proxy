<?php

namespace Webman\Midjourney\Controller;

use Webman\Midjourney\BusinessException;
use Webman\Midjourney\Discord;
use Webman\Midjourney\Task;
use Workerman\Http\Client;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class Image extends Base
{

    /**
     * 画图
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function imagine(Request $request): Response
    {
        $prompt = $request->post('prompt', null);
        if (!is_string($prompt) || $prompt === '') {
            throw new BusinessException('prompt is invalid');
        }
        $notifyUrl = $request->post('notifyUrl', null);
        if ($notifyUrl !== null && !is_scalar($notifyUrl)) {
            throw new BusinessException('notifyUrl is invalid');
        }
        $images = $request->post('images', []);
        if (!$this->invalidImages($images)) {
            throw new BusinessException('images is invalid');
        }
        $data = $request->post('data', []);
        if ($data !== null && !is_array($data)) {
            throw new BusinessException('data is invalid');
        }
        $task = new Task(Task::ACTION_IMAGINE);
        $task->images($images);
        $task->prompt($prompt);
        if ($notifyUrl) {
            $task->notifyUrl($notifyUrl);
        }
        $task->save();
        Discord::submit($task);
        return $this->json($task->id());
    }


    public function action(Request $request)
    {
        $taskId = $request->post('taskId');
        $task = Task::get($taskId);
        if (!$task) {
            throw new BusinessException('任务不存在');
        }
        $notifyUrl = $request->post('notifyUrl', null);
        if ($notifyUrl !== null && !is_scalar($notifyUrl)) {
            throw new BusinessException('notifyUrl is invalid');
        }
        $customId = $request->post('customId', '');
        $jobNames = [
            'MJ::JOB::upsample' => Task::ACTION_UPSCALE,
            'MJ::JOB::variation' => Task::ACTION_VARIATION,
            'MJ::JOB::reroll' => Task::ACTION_REROLL,
            'MJ::Outpaint' => Task::ACTION_ZOOMOUT,
            'MJ::JOB::pan_left' => Task::ACTION_PANLEFT,
            'MJ::JOB::pan_right' => Task::ACTION_PANRIGHT,
            'MJ::JOB::pan_up' => Task::ACTION_PANUP,
            'MJ::JOB::pan_down' => Task::ACTION_PANDOWN,
            'MJ::CustomZoom' => Task::ACITON_ZOOMOUT_CUSTOM,
            'MJ::JOB::upsample_v5_2x' => Task::ACTION_UPSCALE_V5_2X,
            'MJ::JOB::upsample_v5_4x' => Task::ACTION_UPSCALE_V5_4X,
            'MJ::JOB::upsample_v6_2x_subtle' => Task::ACTION_UPSCALE_V6_2X_SUBTLE,
            'MJ::JOB::upsample_v6_2x_creative' => Task::ACTION_UPSCALE_V6_2X_CREATIVE,
            'MJ::JOB::low_variation' => Task::ACTION_VARIATION_SUBTLE,
            'MJ::JOB::high_variation' => Task::ACTION_VARIATION_STRONG,
            'MJ::Inpaint' => Task::ACTION_VARIATION_REGION,
            'MJ::Job::PicReader' => Task::ACTION_PIC_READER,
            'MJ::CancelJob::ByJobid' => Task::ACTION_CANCEL_JOB,
        ];
        $action = '';
        foreach ($jobNames as $jobName => $taskAction) {
            if (strpos($customId, $jobName) === 0) {
                $action = $taskAction;
                break;
            }
        }
        if (!$action) {
            throw new BusinessException('action not found');
        }
        $prompt = $request->post('prompt');
        $mask = $request->post('mask');
        if ($action === Task::ACTION_VARIATION_REGION && (!is_string($mask) || $mask === '')) {
            throw new BusinessException('mask is required');
        }
        $needPrompt = in_array($action, [Task::ACTION_PIC_READER, Task::ACITON_ZOOMOUT_CUSTOM, Task::ACTION_VARIATION_REGION]);
        if ($needPrompt && (!is_string($prompt) || $prompt === '')) {
            throw new BusinessException('prompt is required');
        }
        $prompt = $needPrompt ? $prompt : $task->prompt();
        $newTask = new Task($action);
        $params = [
            'customId' => $customId,
            'messageId' => $task->messageId()
        ];
        if ($action === Task::ACTION_UPSCALE) {
            $items = explode('::', $customId);
            $params['index'] = $items[3] ?? 1;
        }
        if ($action === Task::ACTION_VARIATION_REGION) {
            $params['mask'] = $mask;
        }
        if ($notifyUrl) {
            $newTask->notifyUrl($notifyUrl);
        }
        $newTask->params($params);
        $newTask->discordId($task->discordId());
        $newTask->prompt($prompt);
        $newTask->data(['uid' => time()]);
        $newTask->save();
        Discord::submit($newTask);
        return $this->json($newTask->id());
    }

}