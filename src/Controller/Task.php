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

namespace Webman\Midjourney\Controller;

use Webman\Midjourney\Task as TaskService;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class Task
{
    public function fetch(Request $request)
    {
        $id = $request->get('taskId');
        if (!$id || !$task = TaskService::get($id)) {
            return new Response(200, [
                'Content-Type' => 'application/json'
            ], json_encode([
                'code' => 404,
                'msg' => '未找到任务', 'data' => null
            ]));
        }
        return new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'code' => 0,
            'msg' => 'success',
            'data' => $task->toArray()
        ]));
    }
}