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

class Config
{

    protected static $config = [];

    public static function get($key = null, $default = null)
    {
        if ($key === null) {
            return static::$config;
        }
        $keyArray = explode('.', $key);
        $value = static::$config;
        foreach ($keyArray as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }

    public static function init($config)
    {
        static::$config = $config;
    }
}