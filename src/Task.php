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

namespace Webman\Midjourney;

use Throwable;
use Webman\Midjourney\Service\Image;

class Task
{
    const STATUS_PENDING = 'PENDING';
    const STATUS_STARTED = 'STARTED';
    const STATUS_SUBMITTED = 'SUBMITTED';
    const STATUS_RUNNING = 'RUNNING';
    const STATUS_FINISHED = 'FINISHED';
    const STATUS_FAILED = 'FAILED';

    const ACTION_IMAGINE = 'IMAGINE';
    const ACTION_UPSCALE = 'UPSCALE';
    const ACTION_VARIATION = 'VARIATION';
    const ACTION_REROLL = 'REROLL';
    const ACTION_DESCRIBE = 'DESCRIBE';
    const ACTION_BLEND = 'BLEND';
    const ACTION_PANLEFT = 'PANLEFT';
    const ACTION_PANRIGHT = 'PANRIGHT';
    const ACTION_PANUP = 'PANUP';
    const ACTION_PANDOWN = 'PANDOWN';
    const ACTION_MAKE_SQUARE = 'MAKE_SQUARE';
    const ACTION_ZOOMOUT = 'ZOOMOUT';
    const ACITON_ZOOMOUT_CUSTOM= 'ZOOMOUT_CUSTOM';
    const ACTION_PIC_READER = 'PIC_READER';
    const ACTION_CANCEL_JOB = 'CANCEL_JOB';

    const ACTION_UPSCALE_V5_2X = 'UPSCALE_V5_2X';
    const ACTION_UPSCALE_V5_4X = 'UPSCALE_V5_4X';
    const ACTION_UPSCALE_V6_2X_CREATIVE = 'UPSCALE_V6_2X_CREATIVE';
    const ACTION_UPSCALE_V6_2X_SUBTLE = 'UPSCALE_V6_2X_SUBTLE';

    const ACTION_VARIATION_STRONG = 'VARIATION_STRONG';
    const ACTION_VARIATION_SUBTLE = 'VARIATION_SUBTLE';
    const ACTION_VARIATION_REGION = 'VARIATION_REGION';


    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var string
     */
    protected $status = self::STATUS_PENDING;

    /**
     * @var string
     */
    protected $nonce;

    /**
     * @var string
     */
    protected $prompt;

    /**
     * @var string
     */
    protected $finalPrompt;

    /**
     * @var string
     */
    protected $notifyUrl;

    /**
     * @var int
     */
    protected $submitTime;

    /**
     * @var int
     */
    protected $startTime;

    /**
     * @var int
     */
    protected $finishTime;

    /**
     * @var string
     */
    protected $progress;

    /**
     * @var string
     */
    protected $imageUrl;

    /**
     * @var string
     */
    protected $imageRawUrl;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $failReason;

    /**
     * @var string
     */
    protected $messageId;

    /**
     * @var string
     */
    protected $messageHash;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var array
     */
    protected $images = [];

    /**
     * @var array
     */
    protected $attachments = [];

    /**
     * @var array
     */
    protected $buttons = [];

    /**
     * @var bool
     */
    protected $deleted = false;

    /**
     * @var string
     */
    protected $discordId;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var TaskStore\TaskStoreInterface
     */
    protected static $store;


    /**
     * @param array $config
     * @return void
     */
    public static function init(array $config = [])
    {
        static::$store = new $config['handler']($config[$config['handler']], $config['expiredDates']);
    }

    /**
     * @param $action
     */
    public function __construct($action)
    {
        $this->action = $action;
        $this->submitTime = time();
        $this->id = $this->nonce = Discord::uniqId();
        $this->notifyUrl = Config::get('settings.notifyUrl');
    }

    /**
     * @param $id
     * @return $this|string
     */
    public function id($id = null)
    {
        if ($id) {
            $this->id = $id;
            return $this;
        }
        return $this->id;
    }

    /**
     * @param string|null $action
     * @return $this|string
     */
    public function action(string $action = null): string
    {
        if ($action) {
            $this->action = $action;
            return $this;
        }
        return $this->action;
    }

    /**
     * @param $nonce
     * @return $this|string
     */
    public function nonce($nonce = null)
    {
        if ($nonce) {
            $this->nonce = $nonce;
            return $this;
        }
        return $this->nonce;
    }

    /**
     * @param $prompt
     * @return $this|string
     */
    public function prompt($prompt = null)
    {
        if ($prompt) {
            $this->prompt = $prompt;
            return $this;
        }
        return $this->prompt;
    }

    /**
     * @param $finalPrompt
     * @return $this|string
     */
    public function finalPrompt($finalPrompt = null)
    {
        if ($finalPrompt) {
            $this->finalPrompt = $finalPrompt;
            return $this;
        }
        return $this->finalPrompt;
    }

    /**
     * @param $progress
     * @return $this|string
     */
    public function progress($progress = null)
    {
        if ($progress) {
            $this->progress = $progress;
            return $this;
        }
        return $this->progress;
    }

    /**
     * @param $description
     * @return $this|string
     */
    public function description($description = null)
    {
        if ($description) {
            $this->description = $description;
            return $this;
        }
        return $this->description;
    }

    /**
     * @param $messageId
     * @return $this|string
     */
    public function messageId($messageId = null)
    {
        if ($messageId) {
            $this->messageId = $messageId;
            return $this;
        }
        return $this->messageId;
    }

    /**
     * @param $messageHash
     * @return $this|string
     */
    public function messageHash($messageHash = null)
    {
        if ($messageHash) {
            $this->messageHash = $messageHash;
            return $this;
        }
        return $this->messageHash;
    }


    public function params($params = null)
    {
        if ($params) {
            $this->params = $params;
            return $this;
        }
        return $this->params;
    }

    public function data($data = null)
    {
        if ($data !== null) {
            $this->data = $data;
            return $this;
        }
        return $this->data;
    }

    public function buttons(array $buttons = null)
    {
        if ($buttons !== null) {
            $this->buttons = $buttons;
            return $this;
        }
        return $this->buttons;
    }

    public function failReason($reason = null)
    {
        if ($reason) {
            $this->failReason = $reason;
            return $this;
        }
        return $this->failReason;
    }

    public function submitTime($time = null)
    {
        if ($time) {
            $this->submitTime = $time;
            return $this;
        }
        return $this->submitTime;
    }

    public function startTime($time = null)
    {
        if ($time) {
            $this->startTime = $time;
            return $this;
        }
        return $this->startTime;
    }

    public function finishTime($time = null)
    {
        if ($time) {
            $this->finishTime = $time;
            return $this;
        }
        return $this->finishTime;
    }

    public function notifyUrl($url = null)
    {
        if ($url) {
            $this->notifyUrl = $url;
            return $this;
        }
        return $this->notifyUrl;
    }

    public function status($status = null)
    {
        if ($status) {
            $this->status = $status;
            return $this;
        }
        return $this->status;
    }

    public function imageUrl($url = null)
    {
        if ($url) {
            $this->imageUrl = $url;
            return $this;
        }
        return $this->imageUrl;
    }

    public function imageRawUrl($url = null)
    {
        if ($url) {
            $this->imageRawUrl = $url;
            return $this;
        }
        return $this->imageRawUrl;
    }

    public function images(array $images = [])
    {
        if ($images) {
            $this->images = $images;
            return $this;
        }
        return $this->images;
    }

    public function attachments(array $images = [])
    {
        if ($images) {
            $this->attachments = $images;
            return $this;
        }
        return $this->attachments;
    }

    public function discordId(string $discordId = null)
    {
        if ($discordId) {
            $this->discordId = $discordId;
            return $this;
        }
        return $this->discordId;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'status' => $this->status,
            'submitTime' => $this->submitTime,
            'startTime' => $this->startTime,
            'finishTime' => $this->finishTime,
            'progress' => $this->progress,
            'imageUrl' => $this->imageUrl,
            'imageRawUrl' => $this->imageRawUrl,
            'prompt' => $this->prompt,
            'finalPrompt' => $this->finalPrompt,
            'params' => $this->params,
            'images' => $this->images,
            'attachments' => $this->attachments,
            'description' => $this->description,
            'failReason' => $this->failReason,
            'messageHash' => $this->messageHash,
            'nonce' => $this->nonce,
            'messageId' => $this->messageId,
            'discordId' => $this->discordId,
            'data' => $this->data,
            'buttons' => $this->buttons,
        ];
    }

    public function save()
    {
        if ($this->deleted) {
            return $this;
        }
        static::$store->save($this);
        return $this;
    }

    public function delete()
    {
        $this->deleted = true;
        static::$store->delete($this->id);
    }

    public static function get($taskId)
    {
        return static::$store->get($taskId);
    }

    public static function getList($listName): array
    {
        $list = static::$store->getList($listName);
        $tasks = [];
        foreach ($list as $taskId) {
            $task = static::$store->get($taskId);
            if ($task) {
                $tasks[$taskId] = $task;
            } else {
                static::$store->removeFromList($listName, $taskId);
                unset($list[$taskId]);
            }
        }
        return $tasks;
    }

    public function addToList($listName): Task
    {
        static::$store->addTolist($listName, $this->id());
        return $this;
    }

    public function removeFromList($listName): Task
    {
        static::$store->removeFromList($listName, $this->id());
        return $this;
    }

    public function addAttachment($index, array $attachment): Task
    {
        $this->attachments[$index] = $attachment;
        ksort($this->attachments);
        return $this;
    }

    public function attachmentsReady(): bool
    {
        return count($this->attachments) === count($this->images);
    }

    /**
     * @return false|string
     */
    public function __toString()
    {
        return json_encode(array_merge($this->toArray(), [
            'notifyUrl' => $this->notifyUrl,
        ]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }


    /**
     * @param string $jsonString
     * @return Task | null
     */
    public static function unserialize(string $jsonString): ?Task
    {
        $data = json_decode($jsonString, true);
        if (!$data) {
            return null;
        }
        $task = new static($data['action']);
        $task->id($data['id']);
        $task->status($data['status']);
        $task->submitTime($data['submitTime']);
        $task->startTime($data['startTime']);
        $task->finishTime($data['finishTime']);
        $task->progress($data['progress']);
        $task->imageUrl($data['imageUrl']);
        $task->imageRawUrl($data['imageRawUrl']);
        $task->failReason($data['failReason']);
        $task->prompt($data['prompt']);
        $task->finalPrompt($data['finalPrompt']);
        $task->params($data['params']);
        $task->messageHash($data['messageHash']);
        $task->nonce($data['nonce']);
        $task->messageId($data['messageId']);
        $task->discordId($data['discordId']);
        $task->images($data['images'] ?? []);
        $task->attachments($data['attachments'] ?? []);
        $task->notifyUrl($data['notifyUrl']);
        $task->description($data['description'] ?? null);
        $task->buttons($data['buttons'] ?? []);
        $task->data($data['data'] ?? []);
        return $task;
    }
}