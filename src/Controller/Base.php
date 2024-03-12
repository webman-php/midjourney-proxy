<?php

namespace Webman\Midjourney\Controller;


use Workerman\Protocols\Http\Response;

class Base
{
    /**
     * @param $taskId
     * @param array $data
     * @param int $code
     * @param string $msg
     * @return Response
     */
    public function json($taskId, array $data = [], int $code = 0, string $msg = 'ok'): Response
    {
        $data = [
            'code' => $code,
            'msg' => $msg,
            'taskId' => $taskId,
            'data' => $data
        ];
        return  new Response(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param $images
     * @return bool
     */
    public function invalidImages($images): bool
    {
        if (!is_array($images)) {
            return false;
        }
        foreach ($images as $image) {
            if (!is_string($image) || !filter_var($image, FILTER_VALIDATE_URL)) {
                return false;
            }
        }
        return true;
    }
}