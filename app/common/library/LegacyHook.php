<?php

namespace app\common\library;

use think\facade\Event;

class LegacyHook
{
    public static function listen($tag, &$params = null, $extra = null, $once = false)
    {
        $results = Event::trigger($tag, $params);
        if ($once) {
            return is_array($results) && $results ? end($results) : null;
        }
        return $results;
    }

    public static function add($tag, $listener, $first = false)
    {
        Event::listen($tag, $listener, $first);
    }

    public static function get($tag)
    {
        return Event::hasListener($tag) ? [$tag] : [];
    }
}
