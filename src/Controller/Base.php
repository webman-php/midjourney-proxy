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


use Webman\Midjourney\BusinessException;
use Webman\Midjourney\Discord;
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
    protected function json($taskId, array $data = [], int $code = 0, string $msg = 'ok'): Response
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
    protected function invalidImages($images): bool
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

    /**
     * @param $request
     * @param ...$args
     * @return array
     * @throws BusinessException
     */
    protected function input($request, ...$args)
    {
        $input = [];
        foreach ($args as $arg) {
            $explode = explode('|', $arg);
            $field = $explode[0];
            $required = in_array('required', $explode);
            $value = $request->post($field);
            if ($required && !$value) {
                throw new BusinessException($field . ' is required');
            }
            switch ($field) {
                case 'prompt':
                    if ($this->containBannedWords($value)) {
                        throw new BusinessException('出于政策隐私和安全的考虑，我们无法生成相关内容');
                    }
                    $input[] = $value ? preg_replace('/\s+/', ' ', $value) : $value;
                    break;
                case 'images':
                    $value = $value ?: [];
                    if (!$this->invalidImages($value)) {
                        throw new BusinessException('images is invalid');
                    }
                    $input[] = $value;
                    break;
                case 'dimensions':
                    $value = $value ?: [];
                    $dimensions = [
                        'PORTRAIT' => Discord::DIMENSIONS_PORTRAIT,
                        'SQUARE' => Discord::DIMENSIONS_SQUARE,
                        'LANDSCAPE' => Discord::DIMENSIONS_LANDSCAPE,
                    ];
                    if ($value && !isset($dimensions[$value])) {
                        throw new BusinessException('dimensions is invalid');
                    }
                    $input[] = $dimensions[$value];
                    break;
                case 'data':
                    if ($value !== null && !is_array($value)) {
                        throw new BusinessException('data is invalid');
                    }
                    $input[] = $value;
                    break;
                case 'customId':
                    if (!is_string($value) || strpos($value, '::') === false) {
                        throw new BusinessException('customId is invalid');
                    }
                    $input[] = $value;
                    break;
                case 'taskId':
                    if (!is_string($value) || !preg_match('/\d{19}/', $value)) {
                        throw new BusinessException('taskId is invalid');
                    }
                    $input[] = $value;
                    break;
                case 'mask':
                    if ($value !== null && !is_string($value) || $value === '') {
                        throw new BusinessException('mask is invalid');
                    }
                    $input[] = $value;
                    break;
                default:
                    $input[] = $value;
                    break;
            }
        }
        return $input;
    }

    /**
     * 包含禁用词
     * @param $prompt
     * @return bool
     */
    protected function containBannedWords($prompt): bool
    {
        $bannedWordsFile = base_path('config/plugin/webman/midjourney/banned-words.txt');
        if (!$prompt || !file_exists($bannedWordsFile)) {
            return false;
        }
        $bannedWords  = file($bannedWordsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($bannedWords as $bannedWord) {
            $pattern = '/\b' . preg_quote($bannedWord, '/') . '\b/';
            if (preg_match($pattern, $prompt)) {
                return true;
            }
        }
        return false;
    }

}
