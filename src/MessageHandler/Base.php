<?php

namespace Webman\Midjourney\MessageHandler;

class Base
{
    protected static $regex = [
        '/.*?\*\*(.*?)\*\*.+<@\d+> \((.*?)\)/'
    ];

    /**
     * @param $content
     * @return string
     */
    public static function parseContent($content): string
    {
        if ($content === '') {
            return '';
        }
        foreach (static::$regex as $preg) {
            if (preg_match($preg, $content, $matches)) {
                return $matches[1];
            }
        }
        return '';
    }
}