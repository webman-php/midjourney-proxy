<?php

namespace Webman\Midjourney;

use support\Log as MonoLog;
use Workerman\Worker;

class Log
{
    const ERROR_LOG_CHANNEL = 'plugin.webman.midjourney.error';

    const INFO_LOG_CHANNEL = 'plugin.webman.midjourney.info';

    const DEBUG_LOG_CHANNEL = 'plugin.webman.midjourney.debug';

    public static function debug($content)
    {
        if (Config::get('settings.debug') === false) {
            return;
        }
        if (class_exists(MonoLog::class, 'Log')) {
            MonoLog::channel(static::DEBUG_LOG_CHANNEL)->debug($content);
        }
        if (!Worker::$daemonize) {
            echo date('Y-m-d H:i:s') . " " . $content . "\n";
        }
    }

    public static function error($content)
    {
        if (class_exists(MonoLog::class, 'Log')) {
            MonoLog::channel(static::ERROR_LOG_CHANNEL)->error($content);
        }
        static::info($content);
        static::debug($content);
    }

    public static function info($content)
    {
        if (class_exists(MonoLog::class, 'Log')) {
            MonoLog::channel(static::INFO_LOG_CHANNEL)->info($content);
        }
        static::debug($content);
    }
}