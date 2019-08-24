<?php

/*
    PRIVATE
    - cache             gets kirby cache object
    - group             get group by id
    - registerGroup
    - isGroupModified
*/

namespace Bnomei;

use Kirby\Cache\Cache;
use Kirby\Cms\Files;
use Kirby\Cms\Pages;
use Kirby\Toolkit\A;
use function time;

class Modified
{
    private static $indexname = null;
    private static $cache = null;
    private static function cache(): Cache
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


    private static function getGroup(string $group)
    {
        return static::cache()->get(static::$indexname . '-' . sha1($group));
    }

    private static function setGroup(string $group, $data)
    {
        return static::cache()->set(static::$indexname . '-' . sha1($group), $data);
    }

    /*
     *  PUBLIC
     */

    public static function flush()
    {
        return static::cache()->flush();
    }

    private const EXPIRE = 'expire';
    private const OBJECTS = 'objects';
    private const TIMESTAMPS = 'timestamps';
    private const AUTOID = 'autoid';
    private const MODIFIED = 'modifed';
    private const GROUP_NEEDS_REFRESH = null;

    public static function registerGroup(string $group, $objects = null, $options = null)
    {
        $config = option('bnomei.autoid.modified');
        if ($options && is_array($options)) {
            $config = array_merge($config, $options);
        }

        // TODO: recursive
        $recursive = boolval(A::get($config, 'recursive'));
        $expire = time() + intval(A::get($config, 'expire'));

        // create list if modified timestamp entires
        $timestamps = [];

        $arraykeys = [];
        $autoidArray = AutoID::array();
        $fieldname = AutoID::fieldname();
        foreach ($objects as $obj) {
            $autoid = (string) trim($obj->$fieldname()->value());
            $a = A::get($autoidArray, $autoid);
            $arraykeys[] = (string) $obj->id();
            if ($a) {
                $timestamps[] = [
                    self::AUTOID => $autoid,
                    self::MODIFIED => A::get($a, AutoID::MODIFIED),
                ];
            }
        }

        $data = [
            self::EXPIRE => $expire,
            self::OBJECTS => $arraykeys, // array_keys($objects->toArray()), <= too slow
            self::TIMESTAMPS => $timestamps,
        ];

        static::setGroup($group, $data);
        return $objects;
    }

    public static function modifiedGroup(string $group)
    {
        $mod = '';
        if ($g = static::getGroup($group)) {
            foreach (A::get($g, self::TIMESTAMPS) as $t) {
                $mod .= A::get($t, self::MODIFIED);
            }
        }
        if (strlen($mod) > 0) {
            return md5($mod);
        }
        return null;
    }

    public static function findGroup(string $group)
    {
        if ($g = static::getGroup($group)) {
            $expire = A::get($g, self::EXPIRE);
            if ($expire <= time()) {
                // unset group in cache
                static::setGroup($group, null);
                // return group 'needs refresh'
                return self::GROUP_NEEDS_REFRESH;
            }
            $autoidArray = AutoID::array();
            foreach (A::get($g, self::TIMESTAMPS) as $t) {
                $autoid = A::get($t, self::AUTOID);
                $a = A::get($autoidArray, $autoid);
                if ($a) {
                    $oldModified = A::get($t, self::MODIFIED);
                    $newModified = A::get($a, AutoID::MODIFIED);
                    // break on any modified entry
                    if ($oldModified != $newModified) {
                        // unset group in cache
                        static::setGroup($group, null);
                        // return group 'needs refresh'
                        return self::GROUP_NEEDS_REFRESH;
                    }
                } else {
                    // entry removed
                    // unset group in cache
                    static::setGroup($group, null);
                    // return group 'needs refresh'
                    return self::GROUP_NEEDS_REFRESH;
                }
            }
            // if not returned by now group is still valid
            $isPageCollection = true;
            $arrayOfObjects = [];
            foreach (A::get($g, self::OBJECTS) as $obj) {
                $object = page($obj);
                if ($object) { // is a page
                    $arrayOfObjects[] = $object;
                } else { // try file
                    if ($object = kirby()->file($obj)) {
                        $isPageCollection = false;
                        $arrayOfObjects[] = $object;
                    }
                }
            }
            if ($isPageCollection) {
                return new Pages($arrayOfObjects);
            } else {
                return new Files($arrayOfObjects);
            }
        }
        // if group not found return 'needs refresh'
        return self::GROUP_NEEDS_REFRESH;
    }
}
