<?php

/*
    PRIVATE
    - cache             gets kirby cache object
    - group             get group by id
    - registerGroup     
    - isGroupModified
*/

namespace Bnomei;

class Modified
{
    private static $indexname = null;
    private static $cache = null;
    private static function cache(): \Kirby\Cache\Cache
    {
        if (!static::$cache) {
            static::$cache = kirby()->cache('bnomei.autoid');
        }
        // create new index table on new version of plugin
        if (!static::$indexname) {
            static::$indexname = 'modified'.str_replace('.', '', kirby()->plugin('bnomei/autoid')->version()[0]);
        }
        return static::$cache;
    }

    private static function group(string $group) 
    {
        return static::cache()->get(static::$indexname . '-' . $group);
    }

    public static function registerGroup($group, $objects, $options) {
         
    }

    public static function isGroupModified($group) {
        if($g = static::group($group)) {

        }
        return null;
    }
}