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
    }

    public static function info($content)
    {
        if (class_exists(MonoLog::class, 'Log')) {
            MonoLog::channel(static::INFO_LOG_CHANNEL)->info($content);
        }
        static::debug($content);
    }
}