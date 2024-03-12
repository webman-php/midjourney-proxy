<?php

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