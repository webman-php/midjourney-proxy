<?php

namespace Webman\Midjourney;

use Throwable;
use Webman\Midjourney\Enum\WebsocketCode;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class Server
{

    /**
     * @param $config
     */
    public function __construct($config)
    {
        Config::init($config);
        Task::init($config['store']);
    }

    public function onWorkerStart()
    {
        foreach (Config::get('accounts') as $account) {
            if (isset($account['enable']) && !$account['enable']) {
                continue;
            }
            new Discord($account);
        }
    }

    public function onMessage(TcpConnection $connection, Request $request)
    {
        $path = trim($request->path(), '/');
        if (!strpos($path, '/')) {
            $connection->send($this->notfound());
            return null;
        }
        [$controller, $action] = explode('/', $path, 2);
        $controller = '\\Webman\\Midjourney\\Controller\\' . ucfirst($controller);
        if (!class_exists($controller) || !method_exists($controller, $action)) {
            $connection->send($this->notfound());
            return null;
        }
        $headerSecret = $request->header('mj-api-secret');
        $secret = Config::get('settings.secret');
        if ($secret && $headerSecret !== $secret) {
            $data = [
                'code' => 403,
                'msg' => '403 Api Secret 错误',
                'taskId' => null,
                'data' => []
            ];
            $response =  new Response(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $connection->send($response);
            return null;
        }
        try {
            $response = (new $controller)->$action($request);
        } catch (Throwable $e) {
            Log::error($e);
            $data = [
                'code' => 500,
                'msg' => $e->getMessage(),
                'taskId' => null,
                'data' => []
            ];
            $response =  new Response(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        $connection->send($response);
    }

    protected function notfound(): Response
    {
        return new Response(404, [], '404 Not Found');
    }
}
