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

use Jenssegers\Agent\Agent;
use Throwable;
use Webman\Midjourney\MessageHandler\DescribeSuccess;
use Webman\Midjourney\MessageHandler\Error;
use Webman\Midjourney\MessageHandler\InteractionFailure;
use Webman\Midjourney\MessageHandler\ModalCreateStart;
use Webman\Midjourney\MessageHandler\Progress;
use Webman\Midjourney\MessageHandler\Start;
use Webman\Midjourney\MessageHandler\Success;
use Webman\Midjourney\MessageHandler\UpscaleSuccess;
use Webman\Midjourney\MessageHandler\VaryRegionStart;
use Webman\Midjourney\MessageHandler\VaryRegionProgress;
use Webman\Midjourney\Service\Image;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Http\Client;
use Workerman\Timer;
use Workerman\Worker;

class Discord
{
    const APPLICATION_ID = '936929561302675456';
    const SESSION_ID = '52d9197beda5646c1c52cdd7ff75fdd6';
    const IMAGINE_COMMAND_ID = '938956540159881230';
    const IMAGINE_COMMAND_VERSION = '1237876415471554623';
    const BLEND_COMMAND_ID = '1062880104792997970';
    const BLEND_COMMAND_VERSION = '1237876415471554624';
    const DESCRIBE_COMMAND_ID = '1092492867185950852';
    const DESCRIBE_COMMAND_VERSION = '1237876415471554625';
    const SERVER_URL = "https://discord.com";
    const CDN_URL = "https://cdn.discordapp.com";
    const GATEWAY_URL = "wss://gateway.discord.gg";
    const UPLOAD_URL = "https://discord-attachments-uploads-prd.storage.googleapis.com";

    const INTERACTION_CREATE = 'INTERACTION_CREATE';
    const INTERACTION_FAILURE = 'INTERACTION_FAILURE';
    const MESSAGE_CREATE = 'MESSAGE_CREATE';
    const MESSAGE_UPDATE = 'MESSAGE_UPDATE';
    const MESSAGE_DELETE = 'MESSAGE_DELETE';
    const INTERACTION_IFRAME_MODAL_CREATE = 'INTERACTION_IFRAME_MODAL_CREATE';
    const INTERACTION_MODAL_CREATE = 'INTERACTION_MODAL_CREATE';

    const MESSAGE_OPTION_DISPATCH = 0;
    const MESSAGE_OPTION_HEARTBEAT = 1;
    const MESSAGE_OPTION_IDENTIFY = 2;
    const MESSAGE_OPTION_PRESENCE = 3;
    const MESSAGE_OPTION_VOICE_STATE = 4;
    const MESSAGE_OPTION_RESUME = 6;
    const MESSAGE_OPTION_RECONNECT = 7;
    const MESSAGE_OPTION_MEMBER_CHUNK_REQUEST = 8;
    const MESSAGE_OPTION_INVALIDATE_SESSION = 9;
    const MESSAGE_OPTION_HELLO = 10;
    const MESSAGE_OPTION_HEARTBEAT_ACK = 11;
    const MESSAGE_OPTION_GUILD_SYNC = 12;

    const DIMENSIONS_PORTRAIT = '--ar 2:3';
    const DIMENSIONS_LANDSCAPE = '--ar 3:2';
    const DIMENSIONS_SQUARE = '--ar 1:1';


    protected $id;

    protected $token;

    protected $guildId;

    protected $channelId;

    protected $sessionId;

    protected $useragent;

    protected $concurrency;

    protected $timeoutMinutes;

    public $lastSubmitTime = 0;

    /**
     * @var AsyncTcpConnection
     */
    protected $gatewayConnection;

    /**
     * @var Discord[]
     */
    protected static $instances = [];

    protected $sequence;

    protected $heartbeatTimer = 0;

    protected $heartbeatAck = true;

    /**
     * @param array $account
     */
    public function __construct(array $account)
    {
        $this->id = $this->channelId = $account['channel_id'];
        if (isset(static::$instances[$this->id])) {
            Log::error("DISCORD:{$this->id()}  already exists");
        }
        $this->token = $account['token'];
        $this->guildId = $account['guild_id'];
        $this->useragent = $account['useragent'];
        $this->concurrency = $account['concurrency'];
        $this->timeoutMinutes = $account['timeoutMinutes'];
        static::$instances[$this->id] = $this;
        $this->createWss();
        $this->createTimeoutTimer();
    }

    public function createWss()
    {
        $gateway = static::getGateway();
        $transport = 'tcp';
        if (strpos($gateway, 'wss://') === 0) {
            $gateway = str_replace('wss://', 'ws://', $gateway);
            $transport = 'ssl';
        }
        if (strpos(':', $gateway) === false) {
            $gateway .=  $transport === 'ssl' ? ':443' : ':80';
        }
        $ws = new AsyncTcpConnection("$gateway?encoding=json&v=9&compress=zlib-stream");
        $ws->headers = [
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Sec-Websocket-Extensions' => 'permessage-deflate; client_max_window_bits',
            'User-Agent' => $this->useragent,
            'Origin' => 'https://discord.com',
        ];
        $ws->transport = $transport;
        $ws->onConnect = function() {
            Log::debug("DISCORD:{$this->id()} WSS Connected");
        };
        $ws->onMessage = function (TcpConnection $connection, $data) {
            // 解析discord数据
            try {
                $json = static::inflate($connection, $data);
            } catch (Throwable $e) {
                Log::error("DISCORD:{$this->id()} zlib stream inflate error data:" . bin2hex($data) . " " . $e->getMessage());
                Worker::stopAll();
                return;
            }
            $data = json_decode($json, true);
            $code = $data['op'] ?? null;
            if ($code != Discord::MESSAGE_OPTION_HEARTBEAT_ACK) {
                if (!in_array($data['t'], ['GUILD_MEMBER_LIST_UPDATE', 'PASSIVE_UPDATE_V1', 'READY_SUPPLEMENTAL', 'READY', 'CHANNEL_UPDATE'])) {
                    Log::debug("DISCORD:{$this->id()} WSS Receive Message \n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                } else {
                    Log::debug("DISCORD:{$this->id()} WSS Receive Message " . $data['t'] ?? '');
                }
            }
            // White list
            $titleWhiteList = [
                'Zoom Out',
                'Inpaint'
            ];
            if (in_array($data['t'] ?? '', [Discord::INTERACTION_MODAL_CREATE, Discord::INTERACTION_IFRAME_MODAL_CREATE]) && !in_array($data['d']['title'] ?? '', $titleWhiteList)) {
                file_put_contents(runtime_path('logs/midjourney/midjourney.warning.log'), date('Y-m-d H:i:s') . ' ' . ($data['d']['title'] ?? '') . "\n", FILE_APPEND);
            }
            switch ($code) {
                case Discord::MESSAGE_OPTION_HELLO:
                    $this->handleHello($data);
                    $this->login();
                    break;
                case Discord::MESSAGE_OPTION_DISPATCH:
                    $this->handleDispatch($data);
                    break;
                case Discord::MESSAGE_OPTION_HEARTBEAT_ACK:
                    $this->heartbeatAck = true;
                    break;
            }
        };
        $ws->onError = function (TcpConnection $connection, $err, $code) {
            Log::error("DISCORD:{$this->id()} WSS Error $err $code");
        };
        $ws->onClose = function () {
            Log::info("DISCORD:{$this->id()} WSS Closed");
            $this->gatewayConnection->context->inflator = null;
            $this->heartbeatAck = true;
            $this->gatewayConnection->reconnect(1);
        };
        $ws->connect();
        Log::debug("DISCORD:{$this->id()} WSS Connecting...");
        $this->gatewayConnection = $ws;
    }

    protected function createTimeoutTimer()
    {
        Timer::add(60, function () {
            foreach ($this->getRunningTasks() as $task) {
                if ($task->startTime() + $this->timeoutMinutes * 60 < time()) {
                     $task->removeFromList(static::getRunningListName($this->id));
                     if ($task->status() === Task::STATUS_FINISHED) {
                         continue;
                     }
                     $this->failed($task, "任务超时");
                }
            }
        });
    }

    public function id()
    {
        return $this->id;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function guildId()
    {
        return $this->guildId;
    }

    public function channelId()
    {
        return $this->channelId;
    }

    public function sessionId()
    {
        return $this->sessionId ?? static::SESSION_ID;
    }

    public function useragent()
    {
        return $this->useragent;
    }

    /**
     * @param Task $task
     * @return null
     */
    public static function submit(Task $task)
    {
        $status = $task->status();
        if ($status !== Task::STATUS_PENDING) {
            Log::error("Task:{$task->id()} status($status) is not pending");
            return null;
        }
        $task->addToList(static::getPendingListName());
        static::tryToExecute();
        return null;
    }

    public static function tryToExecute()
    {
        foreach (static::getPendingTasks() as $task) {
            static::execute($task);
        }
    }

    public static function execute(Task $task)
    {
        $discordId = $task->discordId() ?: null;
        if (!$instance = static::getIdleInstance($discordId)) {
            Log::debug("TASK:{$task->id()} DISCORD No idle instance found");
            return;
        }
        $instance->lastSubmitTime = microtime(true);
        $instance->doExecute($task);
    }

    protected static function getPendingTasks(): array
    {
        $listName = static::getPendingListName();
        $tasks = Task::getList($listName);
        ksort($tasks);
        return $tasks;
    }

    protected function getRunningTasks(): array
    {
        $listName = static::getRunningListName($this->id);
        return Task::getList($listName);
    }

    public static function getPendingListName(): string
    {
        return "discord-pending-tasks";
    }

    public static function getRunningListName(string $id): string
    {
        return "discord-$id-running-tasks";
    }

    protected function doExecute(Task $task)
    {
        $task->addToList(static::getRunningListName($this->id))->removeFromList(static::getPendingListName())->discordId($this->id);
        Log::info("TASK:{$task->id()} execute by discord {$this->id}");
        try {
            $task->startTime(time())->status(Task::STATUS_STARTED);
            switch ($task->action()) {
                case Task::ACTION_IMAGINE:
                    Image::imagine($task, $this);
                    break;
                case Task::ACTION_UPSCALE:
                case Task::ACTION_VARIATION:
                case Task::ACTION_VARIATION_STRONG:
                case Task::ACTION_VARIATION_SUBTLE:
                case Task::ACTION_REROLL:
                case Task::ACTION_ZOOMOUT:
                case Task::ACTION_PANLEFT:
                case Task::ACTION_PANRIGHT:
                case Task::ACTION_PANUP:
                case Task::ACTION_PANDOWN:
                case Task::ACTION_MAKE_SQUARE:
                case Task::ACITON_ZOOMOUT_CUSTOM:
                case Task::ACTION_UPSCALE_V5_2X:
                case Task::ACTION_UPSCALE_V5_4X:
                case Task::ACTION_UPSCALE_V6_2X_CREATIVE:
                case Task::ACTION_UPSCALE_V6_2X_SUBTLE:
                case Task::ACTION_PIC_READER;
                    Image::change($task, $this);
                    break;
                case Task::ACTION_DESCRIBE:
                    Image::describe($task, $this);
                    break;
                case Task::ACTION_BLEND:
                    Image::blend($task, $this);
                    break;
                case Task::ACTION_VARIATION_REGION:
                    Image::varyRegion($task, $this);
                    break;
                case Task::ACTION_CANCEL_JOB;
                    Image::cancelJob($task, $this);
                    break;
                default:
                    throw new BusinessException("Unknown action {$task->action()}");
            }
        } catch (Throwable $exception) {
            $this->failed($task, (string)$exception);
        }
    }

    public static function getServer()
    {
        return Config::get('proxy.server') ?? static::SERVER_URL;
    }

    public static function getGateway()
    {
        return Config::get('proxy.gateway') ?? static::GATEWAY_URL;
    }

    public static function finished(Task $task)
    {
        if ($task->status() === Task::STATUS_FAILED) {
            return;
        }
        $task->removeFromList(static::getRunningListName($task->discordId()));
        $task->status(Task::STATUS_FINISHED)->finishTime(time())->progress('100%');
        $task->save();
        static::notify($task);
        static::tryToExecute();
    }

    public static function failed(Task $task, string $reason)
    {
        Log::error("TASK:{$task->id()} FAILED, reason $reason");
        if ($task->status() === Task::STATUS_FAILED) {
            return;
        }
        if ($discordId = $task->discordId()) {
            $task->removeFromList(static::getRunningListName($discordId));
        }
        $task->status(Task::STATUS_FAILED)->finishTime(time())->failReason($reason);
        $task->save();
        static::notify($task);
    }

    /**
     * @return false|mixed|null
     */
    public static function getIdleInstance($instanceId = null)
    {
        $availableInstances = [];
        $sort = [];
        if ($instanceId) {
            if (!isset(static::$instances[$instanceId])) {
                return null;
            }
            $instances = [$instanceId => static::$instances[$instanceId]];
        } else {
            $instances = static::$instances;
        }
        foreach ($instances as $instance) {
            $runningTasks = $instance->getRunningTasks();
            if ($instance->concurrency - count($runningTasks) > 0) {
                $availableInstances[] = $instance;
                $sort[] = $instance->lastSubmitTime;
            }
        }
        if (empty($availableInstances)) {
            return null;
        }
        // 找到一个最空闲的实例
        array_multisort($sort, SORT_ASC, $availableInstances);
        return $availableInstances[0];
    }

    public static function get(?string $instanceId = null)
    {
        if ($instanceId) {
            return static::$instances[$instanceId] ?? null;
        }
        return static::$instances[array_rand(static::$instances)];
    }

    public static function getRunningTaskByCondition(TaskCondition $condition): ?Task
    {
        foreach (static::$instances as $instance) {
            foreach (array_reverse($instance->getRunningTasks()) as $task) {
                if ($condition->match($task)) {
                    return $task;
                }
            }
        }
        return null;
    }

    public static function getMessageHash($message)
    {
        if ($customId = $message['d']['components'][0]['components'][0]['custom_id'] ?? '') {
            if (strpos($customId, 'MJ::CancelJob::ByJobid::') === 0) {
                return substr($customId, strlen('MJ::CancelJob::ByJobid::'));
            }
        }
        $filename = $message['d']['attachments'][0]['filename'] ?? '';
        if (!$filename) {
            return null;
        }
        if (substr($filename, -4) === '.png' || substr($filename, -4) === '.jpg') {
            $pos = strrpos($filename, '_');
            return substr($filename, $pos + 1, strlen($filename) - $pos - 5);
        }
        return explode('_', $filename)[0];
    }

    public static function hasImage($message)
    {
        return isset($message['d']['attachments'][0]['url']);
    }

    public static function attachmentsReady(Task $task): bool
    {
        foreach ($task->attachments() as $attachment) {
            if (!is_array($attachment)) {
                return false;
            }
        }
        return true;
    }

    public static function getButtons($message): array
    {
        $components = $message['d']['components'] ?? null;
        if (!is_array($components)) {
            return [];
        }
        $result = [];
        foreach ($components as $section) {
            $buttons = [];
            foreach ($section['components'] ?? [] as $button) {
                if (!isset($button['custom_id'])) {
                    continue;
                }
                if (strpos($button['custom_id'], 'MJ::BOOKMARK::') !== false || $button['custom_id'] === 'MJ::Job::PicReader::all') {
                    break;
                }
                $buttons[] = $button;
            }
            if ($buttons) {
                $result[] = $buttons;
            }
        }
        return array_values($result);
    }

    public static function notify(Task $task)
    {
        if (!$task->notifyUrl()) {
            return;
        }
        $client = new Client();
        $data = $task->toArray();
        $url = $task->notifyUrl();
        Log::debug("Task::{$task->id()} notify $url \n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $client->request($url, [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'data' => json_encode($data),
            'success' => function ($response) use ($task) {
                Log::debug("NOTIFY RESULT " . $response->getBody());
            },
            'error' => function ($error) use ($task) {
                Log::error("NOTIFY ERROR " . $error);
            }
        ]);
    }

    protected function handleHello($data)
    {
        if ($this->heartbeatTimer) {
            Timer::del($this->heartbeatTimer);
        }
        $this->heartbeatTimer = Timer::add($data['d']['heartbeat_interval']/1000 ?? 41.25, function () {
            if (!$this->heartbeatAck) {
                Log::error("DISCORD:{$this->id()} WSS Heartbeat timeout");
                $this->gatewayConnection->close();
                return;
            }
            $this->heartbeatAck = false;
            if ($this->gatewayConnection->getStatus() === TcpConnection::STATUS_ESTABLISHED) {
                $this->send([
                    'op' => Discord::MESSAGE_OPTION_HEARTBEAT,
                    'd' => $this->sequence,
                ]);
            }
        }, null);
    }

    protected function send($data)
    {
        $data = json_encode($data, true);
        $this->gatewayConnection->send($data);
    }

    public function login()
    {
        $agent = new Agent();
        $agent->setUserAgent($this->useragent);
        $platform = $agent->platform() === 'OS X' ? 'Mac OS X' : $agent->platform();
        $data = [
            'op' => Discord::MESSAGE_OPTION_IDENTIFY,
            'd' => [
                'token' => $this->token,
                'capabilities' => 16381,
                'properties' => [
                    'os' => $platform,
                    'browser' => $agent->browser(),
                    'device' => '',
                    'system_locale' => 'zh-CN',
                    'browser_user_agent' => $this->useragent,
                    'browser_version' => $agent->version($agent->browser()),
                    'os_version' => $agent->version($platform),
                    'referrer' => 'https://www.midjourney.com',
                    'referring_domain' => 'www.midjourney.com',
                    'referrer_current' => '',
                    'referring_domain_current' => '',
                    'release_channel' => 'stable',
                    'client_build_number' => 268600,
                    'client_event_source' => null,
                ],
                'presence' => [
                    'status' => 'online',
                    'since' => 0,
                    'activities' => [],
                    'afk' => false,
                ],
                'compress' => false,
                'client_state' => [
                    'guild_versions' => [],
                    'api_code_version' => 0,
                ],
            ],
        ];
        Log::info("DISCORD:{$this->id()} WSS Send Identify");
        Log::debug("DISCORD:{$this->id()} WSS Send Identify\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->send($data);
        $this->tryToExecute();
    }

    /**
     * @param $data
     * @return void
     */
    protected function handleDispatch($data)
    {
        $sequence = $data['s'] ?? null;
        $this->sequence = $sequence ?? $this->sequence;
        if (!is_array($data['d'])) {
            return;
        }
        $handlers = [
            Error::class,
            InteractionFailure::class,
            Start::class,
            Progress::class,
            Success::class,
            UpscaleSuccess::class,
            DescribeSuccess::class,
            ModalCreateStart::class,
            VaryRegionStart::class,
            VaryRegionProgress::class,
        ];
        $type = $data['t'] ?? null;
        if ($type === 'READY') {
            $this->sessionId = $data['d']['session_id'];
        }
        $messageId = $data['d']['id'] ?? '';
        $nonce = $data['d']['nonce'] ?? '';
        foreach ($handlers as $handler) {
            try {
                if(call_user_func([$handler, 'handle'], $data)) {
                    $handlerName = substr(strrchr($handler, "\\"), 1);
                    Log::debug("DISCORD:{$this->id()} WSS DISPATCH SUCCESS Handler:$handlerName type:$type MessageId:$messageId Nonce:$nonce");
                    return;
                }
            } catch (Throwable $e) {
                Log::error("DISCORD:{$this->id()} WSS DISPATCH ERROR Handler:$handler " . $e);
            }
        }
    }

    public static function uniqId(): string
    {
        $string = str_replace('.', '', (string)microtime(true));
        return substr(substr($string, 0, 13) . random_int(100000000, 999999999), 0, 19);
    }

    public static function replaceImageCdn($url)
    {
        $cdn = Config::get('proxy.cdn');
        $defaultCdn = Discord::CDN_URL;
        if ($cdn && $defaultCdn) {
            return str_replace($defaultCdn, $cdn, $url);
        }
        return $url;
    }

    public static function replaceUploadUrl($url)
    {
        $cdn = Config::get('proxy.upload');
        $defaultCdn = Discord::UPLOAD_URL;
        if ($cdn && $defaultCdn) {
            return str_replace($defaultCdn, $cdn, $url);
        }
        return $url;
    }

    /**
     * @param $connection
     * @param $buffer
     * @return false|string
     */
    protected static function inflate($connection, $buffer)
    {
        if (!isset($connection->context->inflator)) {
            $connection->context->inflator = \inflate_init(
                ZLIB_ENCODING_DEFLATE
            );
        }
        return \inflate_add($connection->context->inflator, $buffer);
    }
}